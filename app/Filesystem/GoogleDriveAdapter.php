<?php

namespace App\Filesystem;

use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Hypweb\Flysystem\GoogleDrive\GoogleDriveAdapter as BaseGoogleDriveAdapter;
use League\Flysystem\Config;

class GoogleDriveAdapter extends BaseGoogleDriveAdapter
{
    public function __construct(Google_Service_Drive $service, $root = null, $options = [])
    {
        parent::__construct($service, $root, $options);
    }

    public function delete($path)
    {
        if ($file = $this->getFileObject($path)) {
            $name = $file->getName();
            list ($parentId, $id) = $this->splitPath($path);
            $fileId = $file->getId();
            if ($parents = $file->getParents()) {
                $trashFile = new Google_Service_Drive_DriveFile();
                $opts = [];
                $res = false;
                if (count($parents) > 1) {
                    $opts['removeParents'] = $parentId;
                } else {
                    if ($this->getOptionsDeleteAction() === 'delete') {
                        try {
                            $this->getService()->files->delete($fileId);
                        } catch (\Exception $e) {
                            return false;
                        }
                        $res = true;
                    } else {
                        $trashFile->setTrashed(true);
                    }
                }
                if (!$res) {
                    try {
                        $this->getService()->files->update($fileId, $trashFile, $this->applyDefaultParams($opts, 'files.update'));
                    } catch (\Exception $e) {
                        return false;
                    }
                }
                return true;
            }
        }
        return false;
    }

    protected function getOptionsDeleteAction()
    {
        return 'trash';
    }
}
