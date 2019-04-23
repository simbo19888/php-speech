<?php
/**
 * Created by PhpStorm.
 * User: a.iodkovskii
 * Date: 16.04.2019
 * Time: 13:24
 */

namespace app\commands;


use Google\Cloud\Speech\V1\SpeechClient;
use Google\Cloud\Speech\V1\RecognitionAudio;
use Google\Cloud\Speech\V1\RecognitionConfig;
use Google\Cloud\Storage\StorageClient;
use Yii;
use yii\db\Exception;
use yii\console\Controller;

class GoogleController extends Controller
{
    public function selectFile(){
        $query = (new \yii\db\Query())
            ->select([])
            ->from('file_hash')
            ->where(['or', ['status' => 'waiting'], ['status' => 'processing']])
            ->limit(10)
            ->all();
        return $query;
    }
    private function updateHash($id, $result, $status){
        try {
            Yii::$app->db->createCommand()
                ->update('file_hash', [
                    'status' => $status,
                    'result' => $result
                ],
                    ['id'=>$id])
                ->execute();
        } catch (Exception $e) {
            $file = fopen(__DIR__ . "/errors.txt","a");
            $message = "{$id}:::{$status}:::{$result}:::{$e}";
            fwrite($file,$message);
            fclose($file);
        }
    }
    public function uploadStorage($fileName ,$fileId){
        $this->updateHash($fileId, "", "processing");
        $bucketName = "staging.zippy-acronym-237307.appspot.com";
        $source = __DIR__ . "/../uploads/wav/{$fileName}";
        $storage = new StorageClient();
        $file = fopen($source, 'r');
        $bucket = $storage->bucket($bucketName);
        $object = $bucket->upload($file, [
            'name' => $fileName
        ]);
        return "gs://$bucketName/$fileName";
    }
    function delete_object($bucketName, $objectName)
    {
        $storage = new StorageClient();
        $bucket = $storage->bucket($bucketName);
        $object = $bucket->object($objectName);
        $object->delete();
    }
    public function getTranscript($uri ,$fileId){
        $audio = (new RecognitionAudio())
            ->setUri($uri);
        # The audio file's encoding, sample rate and language
        $config = new RecognitionConfig([
            'language_code' => 'ru_RU',
        ]);
        $config->setModel('default');
        # Instantiates a client
        $client = new SpeechClient();

        $res = "";
        $status = "success";
        $operation = $client->longRunningRecognize($config, $audio);
        $operation->pollUntilComplete();
        if ($operation->operationSucceeded()) {
            $response = $operation->getResult();
            // each result is for a consecutive portion of the audio. iterate
            // through them to get the transcripts for the entire audio file.
            foreach ($response->getResults() as $result) {
                $alternatives = $result->getAlternatives();
                $mostLikely = $alternatives[0];
                $transcript = $mostLikely->getTranscript();
                $res .= $transcript;
            }
        } else {
            $status = "error";
            $res = $operation->getError();
        }
        $this->updateHash($fileId, strval($res), $status);
        $client->close();
    }
    public function actionIndex()
    {
        #putenv("GOOGLE_APPLICATION_CREDENTIALS=/var/www/zippy-acronym-237307-b5ce852938b8.json");
        $pid = getmypid();
        $file = fopen(__DIR__ . "/pid.txt","w");
        fwrite($file, $pid);
        fclose($file);
        $fileList = $this->selectFile();
        while($fileList!=[]) {
            foreach ($fileList as $file) {
                try {
                    exec("sox " . __DIR__ . "/../uploads/mp3/{$file['hash']}.mp3 -V1 " . __DIR__ . "/../uploads/wav/{$file['hash']}.wav rate 16k channels 1 &");
                } catch (\Exception $e){
                    $this->updateHash($file['id'], "Ошибка при работе с sox", "error");
                    continue;
                }
                try{
                    $uri = $this->uploadStorage("{$file["hash"]}.wav", $file['id']);
                    $this->getTranscript($uri, $file['id']);
                    $this->delete_object("staging.zippy-acronym-237307.appspot.com","{$file['hash']}.wav");
                } catch (\Exception $e){
                    $this->updateHash($file['id'], "Ошибка при работе с google cloud", "error");
                }
                unlink(__DIR__."/../uploads/mp3/{$file['hash']}.mp3");
                unlink(__DIR__."/../uploads/wav/{$file['hash']}.wav");
            }
            $fileList = $this->selectFile();
        }

    }
}
