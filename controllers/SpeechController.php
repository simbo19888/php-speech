<?php

namespace app\controllers;

use yii\web\Response;
use yii\rest\Controller;
use yii\db\Exception;
use yii;

class SpeechController extends Controller
{
    /* Определяет формат возращаемых данных */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['contentNegotiator']['formats'] = ['application/json' => Response::FORMAT_JSON,];
        return $behaviors;
    }

    /* Отключение всех методов кроме GET */
    protected function verbs()
    {
        return[
            'post' => ['POST'],
            'get' => ['GET'],
        ];
    }

    private function uploadFile($key = null)
    {
        $uploadDir = '../uploads/mp3/';
        $filePath = $key !== null ? $_FILES['file']['tmp_name'][$key] : $_FILES['file']['tmp_name'];
        $errorCode = $key !== null ? $_FILES['file']['error'][$key] : $_FILES['file']['error'];
        $name = $key !== null ? basename(strval($_FILES['file']['name'][$key])) : basename(strval($_FILES['file']['name']));
        $hash = md5_file($filePath);
        if ($errorCode !== UPLOAD_ERR_OK) {

            // Массив с названиями ошибок
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'Размер файла превысил значение upload_max_filesize в конфигурации PHP.',
                UPLOAD_ERR_PARTIAL => 'Загружаемый файл был получен только частично.',
                UPLOAD_ERR_FORM_SIZE => 'Размер загружаемого файла превысил значение MAX_FILE_SIZE в HTML-форме.',
                UPLOAD_ERR_NO_FILE => 'Файл не был загружен.',
                UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка.',
                UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск.',
                UPLOAD_ERR_EXTENSION => 'PHP-расширение остановило загрузку файла.',
            ];

            // Зададим неизвестную ошибку
            $unknownMessage = 'При загрузке файла произошла неизвестная ошибка.';

            // Если в массиве нет кода ошибки, скажем, что ошибка неизвестна
            $outputMessage = isset($errorMessages[$errorCode]) ? $errorMessages[$errorCode] : $unknownMessage;

            // Выведем название ошибки
            return ['file' => $name, 'error' => $outputMessage];
        } else {
            move_uploaded_file($filePath, $uploadDir . $hash . '.mp3');
            $hashExists = $this->searchForHash($hash);
            if (!$hashExists) {
                // Кэша не существует
                $id = $this->insertHash($hash);
                $result = $id;
            } else if ($hashExists['status'] == 'error') {
                // Кэш с ошибкой
                $result = $this->updateHash($hashExists['id']);
            } else {
                // Файл обрабатывается/обработан
                $result = $hashExists;
            }
            return $result;
        }
    }

    private function insertHash($hash)
    {

        try {
            Yii::$app->db->createCommand()
                ->insert('file_hash', [
                    'hash' => $hash,
                    'status' => 'waiting'
                ])->execute();
        } catch (Exception $e) {
            return $e;
        }
        $return = [
            'id' => intval(Yii::$app->db->lastInsertID),
            'status' => 'waiting'];
        return $return;
    }

    private function updateHash($id){
        try {
            Yii::$app->db->createCommand()
                ->update('file_hash', [
                    'status' => 'waiting',
                ],
                    ['id'=>$id])
                ->execute();
        } catch (Exception $e) {
            return $e;
        }
        return [
            'id' => $id,
            'status' => 'waiting'
        ];
    }

    /* Поиск хеша в базе данных, возвращает id и status если находит, false если не находит */
    private function searchForHash($hash)
    {
        $query = (new \yii\db\Query())
            ->select(['id', 'status', 'result'])
            ->from('file_hash')
            ->where(['hash' => $hash])
            ->one();
        return (boolean)$query ? $query : false;
    }

    public function googleProcessingStart()
    {
        if(file_exists("../commands/pid.txt")){
            $pid = file_get_contents("../commands/pid.txt");
            if(is_numeric($pid)){
                $res = explode(" ",exec("ps -fp $pid"));
                if(end($res) != "google/index"){
                    pclose(popen("php ../yii google/index &", "r"));
                }
            }
            else {
                pclose(popen("php ../yii google/index &", "r"));
            }
        }
        else {
            pclose(popen("php ../yii google/index &", "r"));
        }

    }

    /* Вызывается при обращенни к /speech методом GET. Можно указать id. */
    public function actionGet()
    {
        $queryParams = Yii::$app->request->get();
        if (!isset($queryParams['id'])) {
            $query = (new \yii\db\Query())
                ->select(['id', 'status', 'result'])
                ->from('file_hash')
                ->all();
        } else {
            $query = (new \yii\db\Query())
                ->select(['id', 'status', 'result'])
                ->from('file_hash')
                ->where(['id' => $queryParams['id']])
                ->one();
        }
        return $query;
    }

    /* Основная функция, вызывается при обращенни к /speech */
    public function actionPost()
    {
        $result=[];
        if(is_array($_FILES['file']["error"])){
            foreach($_FILES["file"]["error"] as $key=>$error) {
                array_push($result, $this->uploadFile($key));
            }
        } else {
            array_push($result, $this->uploadFile());
        }
        $this->googleProcessingStart();
        return $result;
    }
}
