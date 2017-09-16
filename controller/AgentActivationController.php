<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\AgentActivation;
use Illuminate\Support\Facades\Validator;

class AgentActivationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $agent_data = AgentActivation::get();
        foreach($agent_data as $key => $row){
            $helpful_links = json_decode($row->helpful_link);
            $agent_data[$key]->link_name = (!empty($helpful_links->link_name[0])) ? $helpful_links->link_name[0]:'';
            $agent_data[$key]->link_url = (!empty($helpful_links->link_url[0])) ? $helpful_links->link_url[0]:'';
            $number_of_rows = (count($helpful_links->link_name) >= count($helpful_links->link_url)) ? count($helpful_links->link_name):count($helpful_links->link_url);

            $helpful_link_html = '';
            if(count($number_of_rows) >= 1){
                for($i=1; $i<$number_of_rows; $i++){
                    if(count($helpful_links->link_name) > 0){
                        foreach($helpful_links->link_name as $k => $val){
                            if($k == $i){
                                $helpful_link_html .= '<div class="date_row"><div class="form-group"><div class="control-label col-sm-3">
<a href="javascript:;" class="btn btn-danger btn-small btn-xs delete-link" style="margin: 0 5px;">
<span class="glyphicon glyphicon-minus"></span></a>Helpful Links</div><div class="col-md-9 col-sm-12"><label class="control-label col-sm-3">Name</label><div class="col-md-9 col-sm-12 padding-off">
<input type="text" name="link_name[]" value="'.$val.'" class="form-control clear-value" placeholder="Link Name"></div>';
                            }
                        }
                    }
                    if(count($helpful_links->link_url) > 0){
                        foreach($helpful_links->link_url as $k => $val){
                            if($k == $i){
                                $helpful_link_html .= '<label class="control-label col-sm-3">Link Url</label><div class="col-md-9 col-sm-12 padding-off margin-top-2">
<input type="text" name="link_url[]" value="'.$val.'" class="form-control clear-value" placeholder="eg http://test.com"></div></div>';
                            }
                        }
                    }
                }
                $agent_data[$key]->custom_links = $helpful_link_html;
            }
        }
        if($request->ajax()){
            return response()->json(view('admin.agent_table', compact('agent_data'))->render());
        }
        return view('admin.agent', compact('agent_data'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = [
            'first_name' => 'required|string|min:2',
            'last_name' => 'string|min:2|nullable',
            'email' => 'required|string|email',
            'website' => 'nullable|url'
        ];
        if(!empty($request->link_url)){
            foreach($request->link_url as $url){
                if(!empty($url)){
                    $rules[''.$url.'_helpful_link'] = 'nullable|string|url';
                    $request->request->add([''.$url.'_helpful_link' => $url]);
                }
            }
        }
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return Response()->json(['status' => 0, 'message' => $validator->errors()->first()]);
        }

        try{
            // File upload
            $image = '';
            if ($request->file('image')) {
                $file = array('image' => $request->file('image'));
                $destinationPath = base_path('public/uploads/agents/'); // upload path
                $extension = $file['image']->getClientOriginalExtension(); // getting image extension
                $fileName = strtotime(date('Y-m-d h:i:s')) . '-' . $file['image']->getClientOriginalName(); // renameing image
                $file['image']->move($destinationPath, $fileName); // uploading file to given path
                $image = $fileName;
            }
            // End File Upload
            $link = [];
            $link['link_name'] = $request->link_name;
            $link['link_url'] = $request->link_url;
            if (!empty($request->id)) {
                $old_image = AgentActivation::where('id', $request->id)->first(['image']);
                $dataArray = [
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'image' => (!empty($image)) ? $image : $old_image->image,
                    'created_at' => date('Y-m-d h:i:s'),
                    'updated_at' => date('Y-m-d h:i:s'),
                    'office_address' => $request->office_address,
                    'website' => $request->website,
                    'content' => $request->page_content,
                    'helpful_link' => json_encode($link),
                    'designation' => $request->designation,
                ];
                $update = AgentActivation::find($request->id)->update($dataArray);
                if (!empty($image) && !empty($old_image->image)) {
                    if (file_exists(base_path('public/uploads/agents/') . $old_image->image)) {
                        unlink(base_path('public/uploads/agents/') . $old_image->image);
                    }
                }
                return response()->json(['status' => 1, 'message' => 'Agent updated successfully.']);
            }
            $dataArray = [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'image' => $image,
                'created_at' => date('Y-m-d h:i:s'),
                'updated_at' => date('Y-m-d h:i:s'),
                'office_address' => $request->office_address,
                'website' => $request->website,
                'content' => $request->page_content,
                'helpful_link' => json_encode($link),
                'designation' => $request->designation,
            ];
            $save = AgentActivation::create($dataArray);
            return response()->json(['status' => 1, 'message' => 'Agent created successfully.']);
        } catch(\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Something went wrong, please try again later.']);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function showAllAgent(Request $request)
    {
        $agent_specialists = AgentActivation::get();
        foreach($agent_specialists as $key => $agent){
            $agent_specialists[$key]->fullname = $agent->first_name.' '.$agent->last_name;
        }
        if($request->is('api/*')){
            return Response()->json($agent_specialists);
        }
        return view('home.agent_list');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $agent_specialists = AgentActivation::where('id', $id)->get();

        foreach($agent_specialists as $key => $row) {
            $helpful_links = json_decode($row->helpful_link);
            $number_of_rows = (count($helpful_links->link_name) >= count($helpful_links->link_url)) ? count($helpful_links->link_name) : count($helpful_links->link_url);
            $helpful_link_html = '';
            if (count($number_of_rows) >= 1) {
                for ($i = 0; $i < $number_of_rows; $i++) {
                    if (count($helpful_links->link_name) > 0) {
                        foreach ($helpful_links->link_name as $k => $val) {
                            if ($k == $i) {
                                $helpful_link_html .= '<li><a href="__" target="_blank">'.$val.'</a></li>';
                            }
                        }
                    }
                    if (count($helpful_links->link_url) > 0) {
                        foreach ($helpful_links->link_url as $k => $val) {
                            if ($k == $i) {
                                if(!empty($val)){
                                    $helpful_link_html = str_replace('__', $val, $helpful_link_html);
                                } else {
                                    $helpful_link_html = str_replace('__', 'javascript:;', $helpful_link_html);
                                    $helpful_link_html = str_replace('_blank', '', $helpful_link_html);
                                }

                            }
                        }
                    }
                }
                $agent_specialists[$key]->custom_links = $helpful_link_html;
            }
        }
        if(count($agent_specialists) <= 0){
            return redirect('/');
        }
        return view('home.agent_detail', compact('agent_specialists'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $delete = AgentActivation::find($id);
        $delete_roles = AgentActivation::where('id', $id)->delete();
        if (!empty($delete->image)) {
            if (file_exists(base_path('public/uploads/agents/') . $delete->image)) {
                unlink(base_path('public/uploads/agents/') . $delete->image);
            }
        }
        return response()->json(['status' => 1]);
    }
}
