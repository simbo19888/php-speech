<?php
/**
 * Created by PhpStorm.
 * User: a.iodkovskii
 * Date: 16.04.2019
 * Time: 13:24
 */

namespace app\commands;

use Google\Cloud\Core\Exception\FailedPreconditionException;
use google\Cloud\Speech\V1;
use Google\Cloud\Speech\V1\SpeechClient;
use Google\Cloud\Speech\V1\RecognitionAudio;
use Google\Cloud\Speech\V1\RecognitionConfig;
use Google\Cloud\Speech\V1\RecognitionConfig\AudioEncoding;
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
            return $e;
        }
    }

    public function getTranscript($fileName ,$fileId){
        $this->updateHash($fileId, "", "processing");
        $audioFile = __DIR__."/../uploads/wav/" . $fileName;
        # get contents of a file into a string
        $content = file_get_contents($audioFile);
        # set string as audio content
        $audio = (new RecognitionAudio())
            ->setContent($content);
        # The audio file's encoding, sample rate and language
        $config = new RecognitionConfig([
            'language_code' => 'ru_RU',
        ]);
        $config->setModel('default');
        # Instantiates a client
        $client = new SpeechClient();
        $res = "";
        try {
            $response = $client->recognize($config, $audio);
            foreach ($response->getResults() as $result) {
                $alternatives = $result->getAlternatives();
                $mostLikely = $alternatives[0];
                $transcript = $mostLikely->getTranscript();
                $res .= strval($transcript);;
            }
        } finally {
            $this->updateHash($fileId, strval($res), "succes");
            $client->close();
        }
    }
    public function actionIndex()
    {
        putenv("GOOGLE_APPLICATION_CREDENTIALS=/var/www/zippy-acronym-237307-15dd4d1ee5f9.json");
        $pid = getmypid();
        $file = fopen(__DIR__ . "/pid.txt","w");
        fwrite($file, $pid);
        fclose($file);
        $fileList = $this->selectFile();
        while($fileList!=[]) {
            foreach ($fileList as $file) {
                pclose(popen("sox ".__DIR__."/../uploads/mp3/{$file['hash']}.mp3 -V1 ".__DIR__."/../uploads/wav/{$file['hash']}.wav rate 16k channels 1 &", "r"));
                $this->getTranscript("{$file['hash']}.wav", $file['id']);

                unlink(__DIR__."/../uploads/mp3/{$file['hash']}.mp3");
                unlink(__DIR__."/../uploads/wav/{$file['hash']}.wav");
            }
            $fileList = $this->selectFile();
        }

    }
}
