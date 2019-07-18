<?php

namespace App\Http\Controllers;

use Exception;
use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class DriveController extends Controller
{
    private $drive;

    public function __construct(Google_Client $client)
    {
        $this->middleware(function ($request, $next) use ($client) {

            if ($client->isAccessTokenExpired()) {
                $client->refreshToken(Auth::user()->refresh_token);
                $client->getAccessToken();
            }

            $client->refreshToken(Auth::user()->google_token);
            $this->drive = new Google_Service_Drive($client);
            return $next($request);
        });
    }

    public function getDrive()
    {
        $this->ListFolders('root');
    }

    public function getChanges()
    {

        $a = $this->drive->changes->getStartPageToken();
        dd($a);

    }

    public function about()
    {

        $a = $this->drive->about->get(['fields' => '*']);
        dd($a);

    }

    public function ListFolders($id)
    {

        $query = "mimeType='application/vnd.google-apps.folder' and '" . $id . "' in parents and trashed=false";

        $optParams = [
            'fields' => 'files(id, name)',
            'q' => $query
        ];

        $results = $this->drive->files->listFiles($optParams);

        if (count($results->getFiles()) == 0) {
            print "No files found.\n";
        } else {
            print "Files:\n";
            foreach ($results->getFiles() as $file) {
                dump($file->getName(), $file->getID());
            }
        }
    }

    function uploadFile(Request $request)
    {
        if ($request->isMethod('GET')) {
            return view('upload');
        } else {
            $this->createFile($request->file('file'));
        }
    }

    function createStorageFile($storage_path)
    {
        $this->createFile($storage_path);
    }

    function createFile($file, $parent_id = null)
    {
        $name = gettype($file) === 'object' ? $file->getClientOriginalName() : $file;
        $fileMetadata = new Google_Service_Drive_DriveFile([
            'name' => $name,
            'parent' => $parent_id ? $parent_id : 'root'
        ]);

        $content = gettype($file) === 'object' ? File::get($file) : Storage::get($file);
        $mimeType = gettype($file) === 'object' ? File::mimeType($file) : Storage::mimeType($file);

        $file = $this->drive->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id'
        ]);

        dd($file->id);
    }

    function deleteFileOrFolder($id)
    {
        try {
            $this->drive->files->delete($id);
        } catch (Exception $e) {
            return false;
        }
    }

    function createFolder($folder_name)
    {
        $folder_meta = new Google_Service_Drive_DriveFile(array(
            'name' => $folder_name,
            'mimeType' => 'application/vnd.google-apps.folder'));
        $folder = $this->drive->files->create($folder_meta, array(
            'fields' => 'id'));
        return $folder->id;
    }

    function getFile()
    {

        $file = $this->drive->files->get('13X6LmOBAeI3C-WnBvQkQfkBTvBzZCiNpwg', ['fields' => 'id, webViewLink, thumbnailLink']);

        dd($file);

    }

    public function listImages()
    {


        $query = 'mimeType=\'application/vnd.google-apps.folder';
//        $query = "mimeType='image/jpeg'";

        $optParams = [
            'fields' => 'files(id, name)',
            'q' => $query
        ];

        $results = $this->drive->files->listFiles($optParams);

        if (count($results->getFiles()) == 0) {
            print "No files found.\n";
        } else {
//            print "Files:\n";
            foreach ($results->getFiles() as $file) {

                $subQuery = "mimeType=\'image/jpeg and '" . $file->getID() . "' in parents and trashed=false";
//        $query = "mimeType='image/jpeg'";

                $subOptParams = [
                    'fields' => 'files(id, name)',
                    'q' => $subQuery
                ];

                $subResults = $this->drive->files->listFiles($subOptParams);

                if (count($results->getFiles()) == 0) {
                    print "No files found.\n";}
                else{

                    foreach ($results->getFiles() as $subFile){

                        dump($subFile->getName(), $subFile->getID());
                    }
                }

            }
        }
    }
}
