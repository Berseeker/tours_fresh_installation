<?php
namespace Modules\Car\Controllers;

use App\Http\Controllers\Controller;
use Modules\Car\Models\Car;
use Illuminate\Http\Request;
use Modules\Location\Models\Location;
use Modules\Review\Models\Review;
use Modules\Core\Models\Attributes;
use DB;

class CarController extends Controller
{
    protected $carClass;
    protected $locationClass;
    public function __construct(Car $carClass, Location $locationClass)
    {
        $this->carClass = $carClass;
        $this->locationClass = $locationClass;
    }

    public function callAction($method, $parameters)
    {
        if(!$this->carClass::isEnable())
        {
            return redirect('/');
        }
        return parent::callAction($method, $parameters); // TODO: Change the autogenerated stub
    }
    public function index(Request $request)
    {

        $layout = setting_item("car_layout_search", 'normal');
        if ($request->query('_layout')) {
            $layout = $request->query('_layout');
        }
        $is_ajax = $request->query('_ajax');
        $for_map = $request->query('_map',$layout === 'map');

        if(!empty($request->query('limit'))){
            $limit = $request->query('limit');
        }else{
            $limit = !empty(setting_item("car_page_limit_item"))? setting_item("car_page_limit_item") : 9;

        }
        $query = $this->carClass->search($request->input());
        $list = $query->paginate($limit);
        $markers = [];
        if (!empty($list) and $for_map) {
            foreach ($list as $row) {
                $markers[] = [
                    "id"      => $row->id,
                    "title"   => $row->title,
                    "lat"     => (float)$row->map_lat,
                    "lng"     => (float)$row->map_lng,
                    "gallery" => $row->getGallery(true),
                    "infobox" => view('Car::frontend.layouts.search.loop-grid', ['row' => $row,'disable_lazyload'=>1,'wrap_class'=>'infobox-item'])->render(),
                    'marker' => get_file_url(setting_item("car_icon_marker_map"),'full') ?? url('images/icons/png/pin.png'),
                ];
            }
        }
        $data = [
            'rows' => $list,
            'layout'=>$layout
        ];
        if ($is_ajax) {
            return $this->sendSuccess([
                "markers" => $markers,
                'fragments'=>[
                    '.ajax-search-result'=>view('Car::frontend.ajax.search-result'.($for_map ? '-map' : ''), $data)->render(),
                    '.result-count'=>$list->total() ? ($list->total() > 1 ? __(":count cars found",['count'=>$list->total()]) : __(":count car found",['count'=>$list->total()])) : '',
                    '.count-string'=> $list->total() ? __("Showing :from - :to of :total Cars",["from"=>$list->firstItem(),"to"=>$list->lastItem(),"total"=>$list->total()]) : ''
                ]
            ]);
        }
        $data = [
            'rows'               => $list,
            'list_location'      => $this->locationClass::where('status', 'publish')->limit(1000)->with(['translation'])->get()->toTree(),
            'car_min_max_price' => $this->carClass::getMinMaxPrice(),
            'markers'            => $markers,
            "blank" => setting_item('search_open_tab') == "current_tab" ? 0 : 1 ,
            "seo_meta"           => $this->carClass::getSeoMetaForPageList()
        ];
        $data['layout'] = $layout;
        $data['attributes'] = Attributes::where('service', 'car')->orderBy("position","desc")->with(['terms'=>function($query){
            $query->withCount('car');
        },'translation'])->get();

        if ($layout == "map") {
            $data['body_class'] = 'has-search-map';
            $data['html_class'] = 'full-page';
            return view('Car::frontend.search-map', $data);
        }
        return view('Car::frontend.search', $data);
    }

    public function detail(Request $request, $slug)
    {
        $row = $this->carClass::where('slug', $slug)->with(['location','translation','hasWishList'])->first();;
        if ( empty($row) or !$row->hasPermissionDetailView()) {
            return redirect('/');
        }
        $translation = $row->translate();
        $car_related = [];
        $location_id = $row->location_id;
        if (!empty($location_id)) {
            $car_related = $this->carClass::where('location_id', $location_id)->where("status", "publish")->take($this->limitRelated ?? 4)->whereNotIn('id', [$row->id])->with(['location','translation','hasWishList'])->get();
        }
        $review_list = $row->getReviewList();
        $data = [
            'row'          => $row,
            'translation'       => $translation,
            'car_related' => $car_related,
            'booking_data' => $row->getBookingData(),
            'review_list'  => $review_list,
            'seo_meta'  => $row->getSeoMetaWithTranslation(app()->getLocale(),$translation),
            'body_class'=>'is_single',
            'breadcrumbs'       => [
                [
                    'name'  => __('Car'),
                    'url'  => route('car.search'),
                ],
            ],
        ];
        $data['breadcrumbs'] = array_merge($data['breadcrumbs'],$row->locationBreadcrumbs());
        $data['breadcrumbs'][] = [
            'name'  => $translation->title,
            'class' => 'active'
        ];
        $this->setActiveMenu($row);
        return view('Car::frontend.detail', $data);
    }
}
