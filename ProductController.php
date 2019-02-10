<?php

namespace App\Http\Controllers\Admin;

use App\Category;
use App\Color;
use App\Maker;
use App\Member;
use App\Order;
use App\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProductController extends Controller
{
    public function getIndex(Request $request)
    {
        if ($request->ajax())
        {
            return  $this->changeStatus($request)->active;
        }
        $rows=Product::all();
        $cats= Category::all();
        $colors= Color::all();
        return view('admin.pages.product.index',compact('rows','cats','colors'));
    }

    public function getAdd(Request $request)
    {
        $cats= Category::all();
        $makers= Maker::all();
        $colors= Color::all();
        return view('admin.pages.product.add',compact('cats','colors','makers'));
    }

    public function postAdd(Request $request)
    {
        $v = validator($request->all(),[
            'name' => 'required|unique:products,name',
            'sku' => 'required|unique:products,sku',
            'color' => 'required|array|min:1',
            'color.*' => 'required',
            'size' => 'required',
            'category_id' => 'required',
            'maker_id' => 'required',
            'active' => 'required',
            'photo' => 'required|array|min:3',
            'photo.*' => 'required|image|mimes:jpeg,jpg,png,gif|max:20000',

        ]);

        if($v->fails()){
            return ['status' => false ,'data' => implode(PHP_EOL ,$v->errors()->all())];
        }
        $inputs = $request->all();
        $destination = public_path('uploads/product');
        if ($request->photo) {
            $arr=[];
            foreach ($request->photo as $image)
            {
                $photo = rand(0000,9999).time().'_'.str_replace(' ','_',$image->getClientOriginalName());
                $image->move($destination, $photo);
                array_push($arr,$photo);
            }
            $inputs['photo']=json_encode($arr);
        }
        $inputs['slug']=str_slug($request->name);
        $inputs['desc']=$request->content1;
        $data=Product::create($inputs);
        if ($data)
        {
            $data->colors()->sync($request->color);
            return [
                'status' => 'success',
                'msg' => 'Saved Successfully',
                'data' => 'Saved Successfully',
            ];
        }
        return [
            'status' => 'error',
            'data' => 'Un Expected Error please try again',
        ];
    }

    public function getEdit($id)
    {
        $row =Product::findorfail($id);
        $cats= Category::all();
        $makers= Maker::all();
        $colors= Color::all();
        return view('admin.pages.product.edit',compact('row','cats','colors','makers'));
    }

    public function postUpdate(Request $request,$id)
    {
        $v = validator($request->all(),[
            'name' => 'required|unique:products,name,'.$id,
            'sku' => 'required|unique:products,sku,'.$id,
            'color' => 'required|array|min:1',
            'color.*' => 'required',
            'size' => 'required',
            'category_id' => 'required',
            'maker_id' => 'required',
            'active' => 'required',
            'photo.*' => 'image|mimes:jpeg,jpg,png,gif|max:20000',

        ]);

        if($v->fails()){
            return ['status' => false ,'data' => implode(PHP_EOL ,$v->errors()->all())];
        }
        $inputs = $request->all();
        $data=Product::findorfail($id);
        $destination = public_path('uploads/product');
        if ($request->photo) {
            $arr=json_decode($data->photo,true);
            foreach ($request->photo as $image)
            {
                $photo = rand(0000,9999).time().'_'.str_replace(' ','_',$image->getClientOriginalName());
                $image->move($destination, $photo);
                array_push($arr,$photo);
            }
            $inputs['photo']=json_encode($arr);
        }
        $inputs['slug']=str_slug($request->name);
        $inputs['desc']=$request->content1;
        $flag=$data->update($inputs);
        if ($flag)
        {
            $data->colors()->sync($request->color);
            return [
                'status' => 'success',
                'msg' => 'Updated Successfully',
                'data' => 'Updated Successfully',
            ];
        }
        return [
            'status' => 'error',
            'data' => 'Un Expected Error please try again',
        ];
    }

    public function getDelete($id)
    {
        $row =Product::findorfail($id);
        $row->trash();
        return [ 'status' => 'success','data'=>'Your imaginary file has been deleted.'];
    }

    public function changeStatus(Request $request)
    {
        $row = Product::findOrfail($request->id);
        return $row->update(['active'=>$request->active]) ? $row : false;
    }

    public function deleteImage(Request $request)
    {
        $row=Product::findorfail($request->id);
        $arr=json_decode($row->photo,true);
        $image = array_pull($arr,$request->index);
        $destination = public_path('uploads/product');
        if (is_file($destination . "/{$image}")) {
            @unlink($destination . "/{$image}");
            $row->update(['photo'=>json_encode($arr)]);
            return 'success';
        }
        return 'error';
    }

    public function getExcel()
    {
        return view('admin.pages.product.excel');
    }

    public function postExcel(Request $request){
        $v = validator($request->all(),[
            'excel' => 'required',
        ]);

        if($v->fails()){
            return ['status' => false ,'data' => implode(PHP_EOL ,$v->errors()->all())];
        }
        if($request->hasFile('excel')){
            try
            {
                Excel::import(new ProductImport(), request()->file('excel'));
            }catch (\Exception $exception)
            {
                return [
                    'status' => false,
                    'data' => 'Failed To Upload Try Again',
                ];
            }
            return [
                'status' => 'success',
                'msg' => 'Saved Successfully',
                'data' => 'Saved Successfully',
            ];
        }
        return [
            'status' => 'error',
            'data' => 'Un Expected Error please try again',
        ];
    }

    public function analysis()
    {
        $products=Product::where('active',1)->orderBy('id','desc')->get();
        $topRate = Product::where('active',1)->whereHas('comments',function ($comment){
            return $comment->where('active',1);
        })->get()->map(function ($product){
            $product->rate = $product->rate();
            return $product;
        });
        $topRate = collect($topRate)->sortByDesc('rate')->take(5);
        $countComment=[];
        foreach ($topRate as $com)
        {
            $countComment[]=$com->comments()->count();
        }
        $allorders =Order::all();
        $total_price= $allorders->sum('total');
        $orders= $allorders->where('status',1)->map(function ($order)
        {
            return array_map(function ($item){
                return $item['attributes']['product_id'];
            },$order->cart);
        });
        $best_ids = [];
        foreach ($orders as $or)
            foreach ($or as $id)
                $best_ids[]=$id;
        $best_ids = array_count_values($best_ids);
        $best_products =Product::find(array_keys($best_ids))->take(5);
        $countBest=collect(array_keys($best_ids))->take(5)->toArray();
        $topMembers =$orders= Order::where('status',1)->pluck('member_id')->toArray();
        $topMembers_count=array_count_values($topMembers);
        $members = Member::find(array_keys($topMembers_count))->take(5);
        return view('admin.pages.product.analysis',
            compact('best_products','countBest','topRate','countComment'
                ,'members','topMembers_count','allorders','total_price')
        );
    }
}
