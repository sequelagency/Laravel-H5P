<?php

namespace Djoudi\LaravelH5p\Http\Controllers;

use App\Http\Controllers\Controller;
use Djoudi\LaravelH5p\Events\H5pEvent;
use Djoudi\LaravelH5p\LaravelH5p;
use H5PEditorEndpoints;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class AjaxController extends Controller
{
    public function libraries(Request $request)
    {
        $machineName = $request->get('machineName');
        $major_version = $request->get('majorVersion');
        $minor_version = $request->get('minorVersion');

        $h5p = App::make('LaravelH5p');
        $core = $h5p::$core;
        $editor = $h5p::$h5peditor;

        if ($machineName) {
            $editor->ajax->action(H5PEditorEndpoints::SINGLE_LIBRARY, $machineName, $major_version, $minor_version, $h5p->get_language(), '', $h5p->get_h5plibrary_url('', true), '');
            // Log library load
            event(new H5pEvent('library', null, null, null, $machineName, $major_version.'.'.$minor_version));
        } else {
            // Otherwise retrieve all libraries
            $editor->ajax->action(H5PEditorEndpoints::LIBRARIES);
        }
    }

    public function singleLibrary(Request $request)
    {
        $h5p = App::make('LaravelH5p');
        $editor = $h5p::$h5peditor;
        $editor->ajax->action(H5PEditorEndpoints::SINGLE_LIBRARY, $request->get('_token'));
    }

    public function contentTypeCache(Request $request)
    {
        $h5p = App::make('LaravelH5p');
        $editor = $h5p::$h5peditor;
        $editor->ajax->action(H5PEditorEndpoints::CONTENT_TYPE_CACHE, $request->get('_token'));
    }

    public function libraryInstall(Request $request)
    {
        $h5p = App::make('LaravelH5p');
        $editor = $h5p::$h5peditor;
        $editor->ajax->action(H5PEditorEndpoints::LIBRARY_INSTALL, $request->get('_token'), $request->get('machineName'));
    }

    public function libraryUpload(Request $request)
    {
        $filePath = $request->file('h5p')->getPathName();
        $h5p = App::make('LaravelH5p');
        $editor = $h5p::$h5peditor;
        $editor->ajax->action(H5PEditorEndpoints::LIBRARY_UPLOAD, $request->get('_token'), $filePath, $request->get('contentId'));
    }

    public function files(Request $request)
    {
        $filePath = $request->file('file');
        $h5p = App::make('LaravelH5p');
        $editor = $h5p::$h5peditor;
        $editor->ajax->action(H5PEditorEndpoints::FILES, $request->get('_token'), $request->get('contentId'));
    }

    public function __invoke(Request $request)
    {
        return response()->json($request->all());
    }

    public function finish(Request $request)
    {
        $user_id = \Auth::id();
        //dd($request);

        if ((int)$user_id > 0) {


            $h5p_url = $request->input('object.id');
            $url_parts = explode('/', $h5p_url);//"http://lmscc.test/api/h5p/embed/13?subContentId=564bbacf-c83a-4511-9831-d8a4af1305eb"
            $h5p_ids = array_pop($url_parts);//13?subContentId=564bbacf-c83a-4511-9831-d8a4af1305eb
            $h5p_ids_parts = explode('?subContentId=', $h5p_ids);

            $h5p_id = $h5p_ids_parts[0];
            $h5p_id_subc = isset($h5p_ids_parts[1]) ? $h5p_ids_parts[1] : null;

            $previous_result = \Djoudi\LaravelH5p\Eloquents\H5pResult::where('content_id', $h5p_id)->where('subcontent_id', $h5p_id_subc)->where('user_id', $user_id)->first();

            $finished = false;
            if ($request->input('verb.id') == "http://adlnet.gov/expapi/verbs/answered") {
                $finished = true;
              
            }

            $result = [
                'content_id' => $h5p_id,
                'subcontent_id' => $h5p_id_subc,
                'user_id' => $user_id,
                'score' => $request->has('result.score.raw') ? $request->input('result.score.raw') : 0,
                'max_score' => $request->has('result.score.max') ? $request->input('result.score.max') : 0,
                'opened' => $previous_result ? $previous_result->opened : now(),
                'finished' => $finished ? now() : null,
                'time' => round(str_replace(['PT', 'S'], '', $request->input('result.duration'))),
                'description' => $request->has('object.definition.description') ? json_encode($request->input('object.definition.description')) : ($request->has('object.definition.name') ? json_encode($request->input('object.definition.name') ): null),
                'correct_responses_pattern' => $request->has('object.definition.correctResponsesPattern') ? json_encode($request->input('object.definition.correctResponsesPattern')) : null,
                'response' => $request->has('result.response') ? json_encode($request->input('result.response')) : null,
                'additionals' => $request->has('object.definition.choices') ? json_encode($request->input('object.definition.choices')) : null
            ];

            if ($previous_result) {//maj
                $previous_result->update($result);

                //remontée sur le parent
                if ($h5p_id_subc) {
                    $parent = \Djoudi\LaravelH5p\Eloquents\H5pResult::where('content_id', $h5p_id)->whereNull('subcontent_id')->where('user_id', $user_id)->first();
                    if ($parent) {
                        $contents = \Djoudi\LaravelH5p\Eloquents\H5pResult::where('content_id', $h5p_id)->whereNotNull('subcontent_id')->where('user_id', $user_id)->get();

                        $data = [];//donnée pour maj parent
                        $data['score'] = 0;
                        $data['max_score'] = 0;
                        $data['finished'] = null;
                        $data['time'] = 0;
                        foreach ($contents as $content) {
                            $data['score'] += $content->score;
                            $data['max_score'] += $content->max_score;
                            if ($content->finished && $data['finished'] != -1) {
                                if ($data['finished'] == null || $data['finished'] < $content->finished) {
                                    $data['finished'] = $content->finished;
                                }   
                            } else {
                                $data['finished'] = -1;
                            }
    
                            $data['time'] = max($data['time'], $content->time);
                        }
    
                        if ($data['finished'] == -1) {
                            $data['finished'] = null;
                        }
    
                        $parent->update($data);
                    }
                }
            } else {
                \Djoudi\LaravelH5p\Eloquents\H5pResult::create($result);
            }

            

        } else {

        }
        
        
        return response()->json($request->all());
    }

    public function contentUserData(Request $request)
    {
        return response()->json($request->all());
    }

    public function dom(Request $request, $id = 0)
    {
        //dd('ici api h5p');
        $h5p = App::make('LaravelH5p');
        $core = $h5p::$core;

        $user = \Auth::user();

        

        //edition
        if ($id > 0) {
         
            //$settings = $h5p::get_core();
            
            $content = $h5p->get_content($id);
            $settings = $h5p::get_editor($content);
            $embed = $h5p->get_embed($content, $settings);
            $embed_code = $embed['embed'];
            $settings = $embed['settings'];
            $title = $content['title'];

            $parameters['params'] = json_decode($content['params']);
            $parameters['metadata'] = $content['metadata'];
            $parameters = \json_encode($parameters);
        } else {
            $content = null;
            $parameters = isset($content['params']) ? $content['params'] : '{}';
            // view Get the file and settings to print from
            
            $embed_code = '';
        }
        
        // Prepare form
        $library = isset($content['library']) ? \H5PCore::libraryToString($content['library']) : 0;
        $display_options = $core->getDisplayOptionsForEdit(isset($content['disable']) ? $content['disable'] : null);
        $title = isset($content['title']) ? $content['title'] : '';

      
       

        return [
            'settings' => $settings,
            'library' => $library,
            'parameters' => $parameters,
            'display_options' => $display_options,
            'embed_code' => $embed_code,
            'title' => $title,
            'user' => [
                'email' => $user->email
            ]
        ];
    }
}
