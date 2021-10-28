<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Content;
use App\Models\User;
use App\Models\ContentImage;
use App\Models\Glass;
use Auth;
use Validator;
use DB;
use Illuminate\Support\Facades\Storage;


class ContentController extends Controller
{

    public function input()
    {
        return view('contents.input');
    }
    
    public function search()
    {
        return view('contents.search');
    }

    public function save(Request $request)
    {
        $userid = Auth::id();
        $input_glass = new Glass();
        $input_glass->maker = $request['maker'];
        $input_glass->model_number = $request['model_number'];
        $input_glass->user_id = $userid;
        $input_glass->year_start = $request['year_start'];
        $input_glass->year_end = $request['year_end'];
        $generation = Glass::getUserGeneration($userid);
        $input_glass->generation = $generation;
        $input_glass->save();
        // ↓編集 フォームから送信されてきたデータとユーザIDをマージし，DBにinsertする
        // $data = $request->merge(['user_id' => Auth::user()->id])->all();
        $input_content = new Content();
        $input_content->content = $request['content'];
        $input_content->glass_id = $input_glass->id;
        $input_content->user_id = $userid;

        $input_content->save();

        if ($request->file('file')) {
            $this->validate($request, [
                'file' => [
                    // 空でないこと
                    'required',
                    // アップロードされたファイルであること
                    'file',
                    // 画像ファイルであること
                    'image',
                    // MIMEタイプを指定
                    'mimes:jpeg,png',
                ]
            ]);


            
            
            if ($request->file('file')->isValid([])) {
                $file_name = $request->file('file')->getClientOriginalName();
                $file_path = Storage::putFile('/images', $request->file('file'), 'public');

                $image_info = new ContentImage();
                $image_info->content_id = $input_content->id;
                $image_info->file_name = $file_name;
                $image_info->file_path = $file_path;
                $image_info->save();
            }
        }
        

        return redirect(route('output'));
    }

    public function output()
    {
        $contents_get_query = Content::select();
        $items = $contents_get_query->get();
        // $names_get_query = User::select('name');
        // $names = $names_get_query->get();
        // $generations_get_query = Glass::select('generation');
        // $generations = $generations_get_query->get();

        // var_dump($generations[1]['generation']);
        // exit();

        foreach ($items as &$item) {
            $file_path = ContentImage::select('file_path')
            ->where('content_id', $item['id'])
            ->first();
            
            if (isset($file_path)) {
                $item['file_path'] = $file_path['file_path'];
            }
            //名前
            $names=User::select('name')
            ->where('id', $item['user_id'])
            ->first();
            $item['name']=$names['name'];
            //世代
            $generations=Glass::select('generation')
            ->where('id', $item['glass_id'])
            ->first();
            $item['generation']=$generations['generation'];
        }
        
        foreach($names as &$name){
            
        }

        return view('contents.output', [
            'items' => $items,
        ]);
    }

    public function mypage()
    {
      //  var_dump(Auth::id());
      //  exit();
        $contents_get_query = Content::select();
        $items = $contents_get_query->get();
        // var_dump($items);
        // exit();

        // var_dump($generations[1]['generation']);
        // exit();

        foreach ($items as &$item) {
            $file_path = ContentImage::select('file_path')
            ->where('content_id', $item['id'])
            ->first();
            
            if (isset($file_path) && ($item['user_id']==Auth::id())) {
                $item['file_path'] = $file_path['file_path'];
            }
            //名前
            $names=User::select('name')
            ->where('id', $item['user_id'])
            ->first();
            $item['name']=$names['name'];
            //世代
            $generations=Glass::select('generation')
            ->where('id', $item['glass_id'])
            ->first();
            $item['generation']=$generations['generation'];
        }
        

        return view('contents.mypage', [
            'items' => $items,
        ]);
    }


    public function detail($content_id)
    {
        $content_get_query = Content::select('*');
        $item = $content_get_query->find($content_id);

        $file_path = ContentImage::select('file_path')
        ->where('content_id', $item['id'])
        ->first();
        if (isset($file_path)) {
            $item['file_path'] = $file_path['file_path'];
        }
        //name
        $names=User::select('name')
        ->where('id', $item['user_id'])
        ->first();
        $item['name']=$names['name'];
        //generation
        $generations=Glass::select('generation')
        ->where('id', $item['glass_id'])
        ->first();
        $item['generation']=$generations['generation'];

        //year_start
        $year_starts=Glass::select('year_start')
        ->where('id', $item['glass_id'])
        ->first();
        $item['year_start']=$year_starts['year_start'];
        //year_end
        $year_ends=Glass::select('year_end')
        ->where('id', $item['glass_id'])
        ->first();
        if($year_ends['year_end']==''){
          $item['year_end']='現在';
        }
        else{
          $item['year_end']=$year_ends['year_end'];
        }
        //maker
        $makers=Glass::select('maker')
        ->where('id', $item['glass_id'])
        ->first();
        $item['maker']=$makers['maker'];
        //model_number
        $model_numbers=Glass::select('model_number')
        ->where('id', $item['glass_id'])
        ->first();
        $item['model_number']=$model_numbers['model_number'];

        return view('contents.detail', [
            'item' => $item,
        ]);
    }

    public function edit($content_id)
    {
        $content_get_query = Content::select('*');
        $item = $content_get_query->find($content_id);

        return view('contents.edit', [
            'item' => $item,
        ]);
    }

    public function update(Request $request)
    {
        $content_get_query = Content::select('*');
        $content_info = $content_get_query->find($request['id']);
        $content_info->content = $request['content'];
        $content_info->save();
        return redirect(route('output'));
    }

    public function delete(Request $request)
    {
        $contents_delete_query = Content::select('*');
        $contents_delete_query->find($request['id']);
        $contents_delete_query->delete();

        $content_images_delete_query = ContentImage::select('*');
        if ($content_images_delete_query->find($request['id'] !== '[]')) {
            $content_images_delete_query->delete();
        }

        return redirect(route('output'));
    }
    public function searched(Request $request)
    {

        // var_dump($request);
        // exit();
        //確認済み
        $validator = Validator::make($request->all(), [
        'search' => 'required | max:250',
      ]);
      if ($validator->fails()) {
        return redirect()
          ->route('tweet.search')
          ->withInput()
          ->withErrors($validator);
      }
      //targetに検索語を格納,sortにソートの値を格納
      $words = $request ->search;
      $array_words = explode(' ',$words);
      //検索対象のquery準備
      $query = DB::table('contents as co')
                      ->select([
                          'co.id',
                          'ci.file_path',
                          'us.name',
                          'gl.generation',
                        ])
                      ->leftjoin('content_images as ci',function($join){
                            $join->on('co.id','=','ci.content_id');
                        })
                      ->leftjoin('glasses as gl',function($join)use($array_words){
                          $join->on('co.glass_id','=','gl.id');
                          foreach($array_words as $word){
                            $join->orWhere('gl.maker','like',"%$word%")->orWhere('gl.model_number','like',"%$word%");
                          }
                        })
                      ->leftjoin('users as us',function($join)use($array_words){
                          $join->on('co.user_id','=','us.id');
                          foreach($array_words as $word){
                            $join->orWhere('us.name','like',"%$word%");
                          }
                        });
      foreach($array_words as $word){
        $query->orWhere('co.content','like',"%$word%");
      }
      $sort = $request ->sort;
      //ソートの並び替えをorder格納
      if($sort == ""){
        $query->orderBy('co.updated_at','desc');
      }else if($sort == "new"){
        $query->orderBy('co.created_at','desc')->orderBy('co.updated_at','desc');
      }else if($sort = "old"){
        $query->orderBy('co.created_at','asc')->orderBy('co.updated_at','asc');
      }
      //絞り込み機能（未完）
      // $sort_check = array(
      //   "userfilter" => 0,
      //   "sincefilter" => 0,
      //   "untilfilter" => 0,
      // );
      // if(preg_match('%flom:%',$words)){
      //   $sort_check["userfilter"] = 1;
      // }
      // if(preg_match('%since:%',$words)){
      //   $sort_check["sincefilter"] = 1;
      // }
      // if(preg_match('%until:%',$words)){
      //   $sort_check["sincefilter"] = 1;
      // }
      // if($sort_check["userfilter"] == 1){
      //   $array_num = array_search('flom:',$array_words);
      //   $array_words[$array_num] = str_replace('flom:','',$array_words[$array_num]);
      //   //Userから名前検索してuser_idを持ってくる
      //   $users = User::where('name','like',"%$array_words[$array_num]%")->pluck('id');
      //   //条件にid追加
      //   $query->where('user_id',$users);
      //   array_splice($array_words,$array_num,1);
      // }
      // if($sort_check["sincefilter"] == 1){
      //   $array_num = array_search('since:',$array_words);
      //   str_replace('since:','',$array_words[$array_num]);
      //   $query->whereColumn('created_at','<',$array_words[$array_num]+' '+$array_words[$array_num+1]);
      //   if($sort_check["untilfilter"] != 1){
      //     array_splice($array_words,$array_num,2);
      //   }
      // }
      // if($sort_check["untilfilter"] == 1){
      //   $array_num = array_search('until:',$array_words);
      //   str_replace('until:','',$array_words[$array_num]);
      //   $query->whereColumn('created_at','>',$array_words[$array_num]+' '+$array_words[$array_num+1]);
      //   array_splice($array_words,$array_num,2);
      // }
      // dd($query->toSql(),$query->getBindings());
      $result = $query->get();
      //一覧画面に検索結果を格納する
      return view('contents.searched',[
        'items' => $result
      ]);
    }
    

    //laraterのマイページ
    // public function mydata()
    // {
    //   // Userモデルに定義した関数を実行する．
    //   $tweets = User::find(Auth::user()->id)->mytweets;
    //   return view('tweet.index', [
    //     'tweets' => $tweets
    //   ]);
    // }
}