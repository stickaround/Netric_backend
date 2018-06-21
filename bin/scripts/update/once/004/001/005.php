<?php
/**
 * Copy any local files up to a file server - mogilefs
 *
 * We moved everything to point to mogilefs in production. In the future we
 * may want to make a general purpose tool for moving from one store to another, but for
 * now this is a one-time shot and all installations must stay on the store they started
 * or they will lose data.
 */
use Netric\Entity\EntityLoaderFactory;
use Netric\Config\ConfigFactory;
use Netric\EntityQuery\Index\IndexFactory;
use Netric\FileSystem\FileStore\LocalFileStoreFactory;
use Netric\FileSystem\FileStore\FileStoreFactory;
use Netric\Log\LogFactory;
use Netric\Db\DbFactory;
use Netric\EntityQuery;

$account = $this->getAccount();
$serviceManager = $account->getServiceManager();
$db = $serviceManager->get(DbFactory::class);
$config = $serviceManager->get(ConfigFactory::class);
$entityIndex = $serviceManager->get(IndexFactory::class);
$localStore = $serviceManager->get(LocalFileStoreFactory::class);
$remoteStore = $serviceManager->get(FileStoreFactory::class);
$entityLoader = $serviceManager->get(EntityLoaderFactory::class);
$log =$serviceManager->get(LogFactory::class);

/*
 * If the store is not local then we need to upload any local files
 */
if ($localStore !== $remoteStore) {
    // Undeleted
    $query = new EntityQuery("file");
    $query->where('dat_ans_key')->equals("");
    $query->andWhere('dat_local_path')->doesNotEqual("");

    // Move undeleted files
    $result = $entityIndex->executeQuery($query);
    $num = $result->getTotalNum();
    for ($i = 0; $i < $num; $i++) {
        $file = $result->getEntity($i);


        // If for some reason the file was deleted while processing
        if (!$file) {
            $log->error("bin/updates/once/005.php tried to load a file but it failed. Skipping.");
            continue;
        }

        // The below should be in a function, but we don't allow functions in update scripts
        $fileName = $config->data_path . DIRECTORY_SEPARATOR . "tmp"  . DIRECTORY_SEPARATOR;
        $fileName .= "file-" . $account->getId() . "-" . $file->getId() . "-" . $file->getValue('revision');

        // Copy to the temp file
        try {
            file_put_contents($fileName, $localStore->readFile($file));

            if (filesize($fileName) <= 0) {
                // Delete temp files made for email attachments
                if ("ematt" === substr($file->getName(), 0, strlen("ematt"))) {
                    $entityLoader->delete($file, true);
                }

                throw new RuntimeException(
                    "Failed to copy file to local temp file: " .
                    $file->getId() . ":" .
                    $file->getName() . " - " . $file->getValue("dat_local_path")
                );
            } else {
                // Save the file to the remote store
                if ($remoteStore->uploadFile($file, $fileName)) {
                    // Cleanup
                    $localStore->deleteFile($file);
                    unlink($fileName);
                } else {
                    throw new RuntimeException("Could not upload: " . $file->getId() . ":" . $file->getName());
                }
            }
        } catch (Exception $ex) {
            $log->error("bin/updates/once/005.php: " . $ex->getMessage());
        }
    }

    // Now move deleted - not the best code but this is a one-time only script
    $query->andWhere("f_deleted")->equals(true);
    $result = $entityIndex->executeQuery($query);
    $num = $result->getTotalNum();
    for ($i = 0; $i < $num; $i++) {
        $file = $result->getEntity($i);

        // The below should be in a function, but we don't allow functions in update scripts
        $fileName = $config->data_path . DIRECTORY_SEPARATOR . "tmp"  . DIRECTORY_SEPARATOR;
        $fileName .= "file-" . $account->getId() . "-" . $file->getId() . "-" . $file->getValue('revision');

        // If for some reason the file was deleted while processing
        if (!$file) {
            $log->error("bin/updates/once/005.php tried to load a file but it failed. Skipping.");
            continue;
        }

        // Copy to the temp file
        try {
            file_put_contents($fileName, $localStore->readFile($file));

            if (filesize($fileName) <= 0) {
                // Delete temp files made for email attachments
                if ("ematt" === substr($file->getName(), 0, strlen("ematt"))) {
                    $entityLoader->delete($file, true);
                }

                throw new RuntimeException(
                    "Failed to copy file to local temp file: " .
                    $file->getId() . ":" .
                    $file->getName() . " - " . $file->getValue("dat_local_path")
                );
            } else {
                // Save the file to the remote store
                if ($remoteStore->uploadFile($file, $fileName)) {
                    // Cleanup
                    $localStore->deleteFile($file);
                    unlink($fileName);
                } else {
                    throw new RuntimeException("Could not upload: " . $file->getId() . ":" . $file->getName());
                }
            }
        } catch (Exception $ex) {
            $log->error("bin/updates/once/005.php: " . $ex->getMessage());
        }
    }
}