<?php
namespace Modules\Report\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\AdminController;
use Modules\Booking\Events\EnquiryReplyCreated;
use Modules\Booking\Models\Enquiry;
use Modules\Booking\Models\EnquiryReply;


class EnquiryController extends AdminController
{
    /**
     * @var Enquiry
     */
    protected $enquiryClass;

    public function __construct(Enquiry $enquiry)
    {
        $this->setActiveMenu(route('report.admin.booking'));
        $this->enquiryClass = $enquiry;

    }

    public function index(Request $request)
    {
        $this->checkPermission('enquiry_view');
        $query = $this->enquiryClass->query()->where('status', '!=', 'draft');
        if (!empty($request->s)) {
            $query->where('email', 'LIKE', '%' . $request->s . '%');
            $query->orderBy('email', 'asc');
            $title_page = __('Search results: ":s"', ["s" => $request->s]);
        }
        $query->whereIn('object_model', array_keys(get_bookable_services()));
        $query->orderBy('id','desc');
        $data = [
            'rows'                  => $query->withCount(['replies'])->paginate(20),
            'breadcrumbs' => [
                [
                    'name' => __('Enquiry'),
                    'url'  => route('report.admin.enquiry.index')
                ],
                [
                    'name'  => __('All'),
                    'class' => 'active'
                ],
            ],
            'enquiry_update'        => $this->hasPermission('enquiry_update'),
            'enquiry_manage_others' => $this->hasPermission('enquiry_manage_others'),
            'statues'        => $this->enquiryClass->enquiryStatus,
            'page_title'=> $title_page ?? __("Enquiry Management")
        ];

        return view('Report::admin.enquiry.index', $data);
    }

    public function bulkEdit(Request $request)
    {
        $ids = $request->input('ids');
        $action = $request->input('action');
        if (empty($ids) or !is_array($ids)) {
            return redirect()->back()->with('error', __('No items selected'));
        }
        if (empty($action)) {
            return redirect()->back()->with('error', __('Please select action'));
        }
        if ($action == "delete") {
            foreach ($ids as $id) {
                $query = $this->enquiryClass->query()->where("id", $id);
                if (!$this->hasPermission('enquiry_manage_others')) {
                    $query->where("vendor_id", Auth::id());
                    $this->checkPermission('enquiry_update');
                }
                $query->first();
                if(!empty($query)){
                    $query->delete();
                }
            }
        } else {
            foreach ($ids as $id) {
                $query = $this->enquiryClass->query()->where("id", $id);
                if (!$this->hasPermission('enquiry_manage_others')) {
                    $query->where("vendor_id", Auth::id());
                    $this->checkPermission('enquiry_update');
                }
                $item = $query->first();
                if(!empty($item)){
                    $item->status = $action;
                    $item->save();
                }
            }
        }
        return redirect()->back()->with('success', __('Update success'));
    }

    public function reply(Enquiry $enquiry,Request  $request){
        $this->checkPermission('enquiry_view');

        $data = [
            'rows'=>$enquiry->replies()->orderByDesc('id')->paginate(20),

            'breadcrumbs' => [
                [
                    'name' => __('Enquiry'),
                    'url'  => route('report.admin.enquiry.index')
                ],
                [
                    'name'  => __('Enquiry :name',['name'=>'#'.$enquiry->id.' - '.($enquiry->service->title ?? '')]),
                ],
                [
                    'name'  => __('All Replies'),
                    'class' => 'active'
                ],
            ],
            'page_title'=>__("Replies"),
            'enquiry'=>$enquiry
        ];

        return view("Report::admin.enquiry.reply",$data);
    }

    public function replyStore(Enquiry $enquiry,Request  $request){
        $this->checkPermission('enquiry_view');

        $request->validate([
            'content'=>'required'
        ]);

        $reply = new EnquiryReply();
        $reply->content = $request->input('content');
        $reply->parent_id = $enquiry->id;
        $reply->user_id = auth()->id();

        $reply->save();

        EnquiryReplyCreated::dispatch($reply,$enquiry);

        return back()->with('success',__("Reply added"));
    }

}
