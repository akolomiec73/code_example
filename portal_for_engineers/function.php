<?php
session_start();
include_once '../class/mysql_PDO.class.php';

$export = json_decode($_POST["json_data"], true);

switch ($export["function"]) {
    /*
     * авторизация пользователей
     */
    case "auth_user":
        $dbase = new Database();
        $dbase->connect();
        $dbase->auth_user($export["login"], $export["password"]);
        $res = $dbase->getResult();
        echo json_encode($res);
        break;
    /*
     * выход пользователя
     */
    case "exit_user":
        $dbase = new Database();
        $dbase->connect();
        $dbase->exit_user();
        $res = $dbase->getResult();
        echo json_encode($res);
        break;
    /*
     * Вывод заявок на ремонт
     */
    case "Show_Application_Repairs":
        $dbase = new Database();
        $dbase->connect();
        $dbase->Show_Application_Repairs($export["folder"]);
        $res = $dbase->getResult();
        echo json_encode($res);
        break;
    /*
     * проверка авторизации
     */
    case "Check_Auth_User":
        $dbase = new Database();
        $dbase->connect();
        $dbase->Check_Auth_User();
        $res = $dbase->getResult();
        echo json_encode($res);
        break;
    /*
     * Добавление сообщений по тикету
     */
    case "Action_Ticket":
        $dbase = new Database();
        $dbase->connect();
        $dbase->Action_Ticket($export["action"], $export["id_ticket"], $export["message"]);
        $res = $dbase->getResult();
        echo json_encode($res);
        break;
    
}
?>