<?php
//Срипт обработки телеграм-бота @evo73rubot
header('Content-Type: text/html; charset=utf-8');
require_once("vendor/autoload.php");
$token = "618599411:AAH8j7ka0pCcDfGNNDy12nOK";
$bot = new \TelegramBot\Api\Client($token);
if(!file_exists("registered.trigger")){ 
    /*
     * файл registered.trigger будет создаваться после регистрации бота. 
     * если этого файла нет значит бот не зарегистрирован 
     */
    $page_url = "https://".$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
    $result = $bot->setWebhook($page_url);
    if($result){
        file_put_contents("registered.trigger",time()); // создаем файл дабы прекратить повторные регистрации
    }
}

//функция подключения к базе данных сайта evo73.ru
function connect_db1(){
    try {
        $db1 = new PDO('mysql:host=host;dbname=evo_www', 'user', 'pass');
        $db1->exec('SET CHARACTER SET utf8');
        $db1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } 
    catch (PDOException $e){
        $status = array('status' => 0, 'description' => 'Database error: ' );
        die (json_encode($status));
    }
    return $db1; 
}

//Информация о состоянии лицевого счёта для пользователя
$bot->on(function($Update) use ($bot){
    $message = $Update->getMessage();
    $mtext = $message->getText();
    //$user_chat = $message->getChat();
    $user_id = $message->getChat()->getId();
    if(ctype_digit($mtext)){ //если сообщение пользователя состоит только из цифр - выполняем запрос
        require '../sql/sql.php'; 
        
        $db_bill = new PDO("oci:dbname=//host" . ';charset=UTF8', 'user', 'pass');//подключение к биллингу
        $db_bill->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $select_money = $db_bill->query("select money_with_ods from bm11.accounts where account_id=".$mtext." ");
        $res_money = $select_money->fetchAll(PDO::FETCH_ASSOC);
        $res_money = round($res_money[0]['MONEY_WITH_ODS'], 2);   

        $bot->sendMessage($message->getChat()->getId(), "Баланс лицевого счета :   ".$res_money." ");

        // Записываем id usera в базу данных для рассылок
        $db = connect_db(); 
        $user = $db -> query("SELECT id_user FROM telegram_users WHERE id_user = {$user_id}") -> fetch(PDO::FETCH_ASSOC);
        if ($user["id_user"]){

        } else {
            $add_record = $db -> prepare("INSERT INTO telegram_users (id_user) VALUES (:id_user)");
            $add_record->bindValue(':id_user',iconv('CP1251','UTF-8',$user_id));
            $add_record->execute();   
        }  
    }
}, function($message) use ($name){
    return true; // когда тут true - команда проходит
});

//Запуск бота
$bot->command('start', function ($message) use ($bot) {
    $answer = 'Для проверки состояния лицевого счета, напишите его номер.
/news - Для просмотра новостей';
    $bot->sendMessage($message->getChat()->getId(), $answer);
});

// Команда показа новостей с сайта
$bot->command('news', function ($message) use ($bot) {
    $db1 = connect_db1(); 

    $news2 = $db1 -> query("SELECT NAME FROM b_iblock_element ORDER BY ID DESC LIMIT 1 ")-> fetchAll(PDO::FETCH_ASSOC);//получение последней новости
    $news2 = $news2[0]['NAME'];

    $news1 = $db1 -> query("SELECT NAME FROM b_iblock_element WHERE id = (SELECT MAX(id) FROM b_iblock_element)-1 LIMIT 1 ")-> fetchAll(PDO::FETCH_ASSOC);//получение предпоследней новости
    $news1 = $news1[0]['NAME'];

    $answer = '<< '.$news1.' >>
<< '.$news2.' >>

Полный список новостей можно посмотреть на сайте
https://www.evo73.ru/news/';

    $bot->sendMessage($message->getChat()->getId(), $answer);     
});
// Рассылка
$bot->command('newpostkolomiec', function ($message) use ($bot) {

    $db1 = connect_db1(); 
    $post = $db1 -> query("SELECT NAME FROM b_iblock_element ORDER BY ID DESC LIMIT 1 ")-> fetchAll(PDO::FETCH_ASSOC);//получение последней новости
    $post = $post[0]['NAME'];

    $answer = '<< '.$post.' >>

Подробности на сайте
https://www.evo73.ru/news/';

    require '../sql/sql.php';  //подключаемся к бд
    $db = connect_db(); 
    $users = $db -> query("SELECT id_user FROM telegram_users ")-> fetchAll(PDO::FETCH_ASSOC);
    $i=0;
    for ($i = 0; $i < count($users); $i++) {
        $bot->sendMessage($users[$i]['id_user'], $answer);
    }  
});
// запускаем обработку
$bot->run();
?>
