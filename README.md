Speech-to-Text PHP-Google
---
## Описание
Программа позволяет распознавать текст из mp3 файла с помощью Google Cloud Speech

## Настройка и необходимое ПО

### Ключ
Программа использует сервисный аккаунт google.
Необходимо указать путь к json файлу в переменных среды(GOOGLE_APPLICATION_CREDENTIALS=path/to/file.json), или подключать в php (в файле controllers/GoogleController.php расскоментировать и изменить путь в 97 строке).
Необходимо указать bucket name от google cloud storage (controllers/GoogleController.php 49)

### Подключение БД
Необходимо настроить подключение к базе данный в файле config/db.php.
Программа использует PostgreSQL 9.6. Единственная таблица имеет название file_hash. 
Таблица имеет следующие поля:

| поле | тип | длина | Не NULL? | Первичный ключ |
| :---: | :---: | :---: | :---: | :---: |
| id     | integer           |       | Да       | Да             |
| hash   | character varying | 32    | Да       | Нет            |
| status | character varying | 10    | Нет      | Нет            |
| result | text              |       | Нет      | Нет            |

Код создания таблицы:


      CREATE TABLE public.file_hash
      (
          id integer NOT NULL DEFAULT nextval('table_id_seq'::regclass),
          hash character varying(32) COLLATE pg_catalog."default" NOT NULL,
          status character varying(10) COLLATE pg_catalog."default",
          result text COLLATE pg_catalog."default",
          CONSTRAINT file_hash_pkey PRIMARY KEY (id)
      )
      WITH (
          OIDS = FALSE
      )
      TABLESPACE pg_default;
      
      ALTER TABLE public.file_hash
          OWNER to postgres;


Код автоинкрементации id:

      CREATE SEQUENCE public.table_id_seq;
      
      ALTER SEQUENCE public.table_id_seq
          OWNER TO postgres;

### Программы
Для конвертации mp3 в wav программа использует sox с поддержкой mp3 (sudo apt-get install libsox-fmt-mp3)

## Запуск
Для начала работы надо отправить POST запрос на host/speech в котором атрибутом file будет являться mp3 файл для обработки.
При отправки GET запроса на host/speech без параметров будет выведен список всех файлов, при отправке GET запроса с параметром id будет выведен результат обработки данного файла.