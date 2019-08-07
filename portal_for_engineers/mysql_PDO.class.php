<?php

@session_start();

class Database {
    private $db_host = 'host';
    private $db_user = 'user';
    private $db_pass = 'pass';
    private $db_name = 'w';

    private $con = false;
    private $result0 = array();

    public function connect() {
        if (!$this->con) {
            try {
                $this->db = new PDO('mysql:host=' . $this->db_host . ';dbname=' . $this->db_name, $this->db_user, $this->db_pass);
                $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->db->exec("set names utf8");
            } catch (PDOException $e) {
                die("Error: " . $e->getMessage());
            }
        } else {
            return true;
        }
    }
/* ====================================================================
 * Авторизация пользователя
 */
    public function auth_user($login, $password) {
        try {
            $query = $this->db->prepare('SELECT us.id, us.region FROM users us WHERE us.login=? AND us.password=? AND us.active=1');
            $password_md5 = md5(md5($password));
            $query->execute(array($login, $password_md5));
            $this->result0 = $query->fetchAll(PDO::FETCH_ASSOC);
            if ($this->result0) {
                //записываем данные пользователя в сессию
                $_SESSION['id_user'] = $this->result0[0]["id"];

                //записываем в лог информацию о входе (или о попытке входа)
                $date_time = date('Y-m-d H:i:s'); // дата и время авторизации
                if (!isset($_SESSION['id_user'])) {
                    $action = "ERROR!!! Вход в систему '" . $login . "' (первые 5 символов пароля: '" . substr($password, 0, 5) . "')";
                    $this->insert_row_log(0, $action, $date_time);
                } else {
                    $action = "Успешная авторизация пользователя " . $login;
                    $this->insert_row_log($_SESSION['id_user'], $action, $date_time);
                }
            }
        } catch (PDOException $e) {
            echo 'Error : ' . $e->getMessage();
            exit();
        }
    } 
/* ====================================================================
 * Выход пользователя
 */
    public function exit_user() {
        $id_user = $_SESSION['id_user'];
        session_start();    //инициализируем механизм сессий
        session_destroy();    //удаляем текущую сессию
        unset($_SESSION['id_user']);
        
        //записываем в лог информацию
        $date_time = date('Y-m-d H:i:s'); // дата и время авторизации
        $action = "Выход из системы";
        $this->insert_row_log($id_user, $action, $date_time);
    }
/* ====================================================================
 * запись данных в лог
 */
    public function insert_row_log($id_user, $action, $date_time) {
        $user_ip = $_SERVER['REMOTE_ADDR'];
        if ($id_user == "") {
            $id_user = 0;
        }
        try {
            $query = $this->db->prepare('INSERT INTO web_tickets_log (`id_user`, `date_time`, `action`, `ip`) VALUES (?, ?, ?, ?)');
            $query->execute(array($id_user, $date_time, $action, $user_ip));
        } catch (PDOException $e) {
            echo 'Error : ' . $e->getMessage();
            exit();
        }
    }
/* ====================================================================
 * Вывод заявок на ремонт
 */
    public function Show_Application_Repairs($folder) {
        $id_user = $_SESSION["id_user"];
        if($id_user == 277){
            $text_sql = 'AND ti.id_montazhnik in (select id from users where id_post = 60)';
        }else {
            $text_sql = 'AND ti.id_montazhnik = '.$id_user.'';
        }
        switch ($folder) {
            case "open_application_list":
                $select = 'SELECT ti.id, ti.ticket_name, ti.text_ticket, ti.id_head_ticket, ti.id_first_ticket, ti.sub_num, ti.performer, ti.date_start, ti.date_last_update, ti.user_last_update, ti.last_message,
                        ti.date_meeting, ti.deadline_note, ti.address, ti.surname_client_individual, ti.name_client_individual, ti.middlename_client_individual, ti.name_client_entity, ti.phone, ti.id_montazhnik, ti.id_theme,
                        ti.deadline AS date_stop, ti.id_state, ti.read_performer AS read_ticket, ti.read_initiator  AS read_other, ti.sms_montazhnik_state, ti.email_montazhnik_state, ti.service,
                        us.surname, us.name, us.middlename, us.last_action AS user_last_action, tist.state_name, tist.ico, tiim.important_name, tiim.star,
                        (SELECT  CONCAT_WS(" ", surname, name, middlename) from users where  id = ti.id_montazhnik) AS mont_name
                        FROM ticket ti, users us, ticket_state tist, ticket_important tiim
                        WHERE (us.id=ti.performer)
                        AND (ti.id_state=tist.id) AND (tiim.id=ti.id_important) AND (ti.id_state>=500) AND (ti.id_state<700) '.$text_sql.'
                        ORDER BY ti.id';
                break;
            case "close_application_list":
                $select = 'SELECT ti.id, ti.ticket_name, ti.text_ticket, ti.id_head_ticket, ti.id_first_ticket, ti.sub_num, ti.performer, ti.date_start, ti.date_last_update, ti.user_last_update,  ti.last_message, ti.date_finish AS date_stop, ti.id_state, ti.id_theme, ti.read_performer AS read_ticket, ti.read_initiator  AS read_other,
                        ti.date_meeting, ti.deadline_note, ti.address, ti.surname_client_individual, ti.name_client_individual, ti.middlename_client_individual, ti.name_client_entity, ti.phone,  ti.id_montazhnik, ti.sms_montazhnik_state, ti.email_montazhnik_state, ti.service,
                        us.surname, us.name, us.middlename, us.last_action AS user_last_action, tist.state_name, tist.ico, tiim.important_name, tiim.star
                        FROM ticket ti, users us, ticket_state tist, ticket_important tiim
                        WHERE (us.id=ti.performer)
                        AND (ti.id_state=tist.id) AND (tiim.id=ti.id_important) AND (ti.id_state>=700) '.$text_sql.'
                        ORDER BY ti.id desc';
                break;
        }
        try {
            $query = $this->db->query($select);
            $this->result0 = $query->fetchAll(PDO::FETCH_ASSOC);
            $count_result = count($this->result0);
            for ($i = 0; $i < $count_result; $i++) {
                //определяем тип строки (для кликанья по большим кнопкам)
                if ($this->result0[$i]["id_state"] >= 500 && $this->result0[$i]["id_state"] < 550) { //новые заявки
                    $this->result0[$i]["row_type"] = "type_new";
                } else if ($this->result0[$i]["id_state"] >= 550 && $this->result0[$i]["id_state"] < 570) { //в работе
                    if (strtotime(date('Y-m-d')) <= strtotime($this->result0[$i]["date_meeting"])) {
                        $this->result0[$i]["row_type"] = "type_injob";
                    } else {
                        $this->result0[$i]["row_type"] = "type_injob type_overdue";
                    }
                }else if ($this->result0[$i]["id_state"] == 570) { //выполненые
                    $this->result0[$i]["row_type"] = "type_finish";

                }
                //просроченные но не принятые в работу заявки
                if ($this->result0[$i]["id_state"] >= 500 && $this->result0[$i]["id_state"] < 550 && strtotime(date('Y-m-d')) > strtotime($this->result0[$i]["date_meeting"])) {
                    $this->result0[$i]["row_type"] = "type_new type_overdue";
                }

                $this->result0[$i]["date_start"] = date('d.m.Y H:i', strtotime($this->result0[$i]["date_start"]));
                $this->result0[$i]["date_stop"] = date('d.m.Y', strtotime($this->result0[$i]["date_stop"]));

                if ($this->result0[$i]["last_message"] == null) {
                    $this->result0[$i]["last_message"] = "-";
                }
                if ($this->result0[$i]["surname_client_individual"] > "") {
                    $this->result0[$i]["client_name"] = $this->result0[$i]["surname_client_individual"] . " " . $this->result0[$i]["name_client_individual"] . " " . $this->result0[$i]["middlename_client_individual"];
                } else if ($this->result0[$i]["name_client_entity"] > "") {
                    $this->result0[$i]["client_name"] = $this->result0[$i]["name_client_entity"];
                } else {
                    $this->result0[$i]["client_name"] = "Без клиента";
                }
                $this->result0[$i]["address"] = $this->Cut_Address($this->result0[$i]["address"]);
            }
        } catch (PDOException $e) {
            echo 'Error : ' . $e->getMessage();
            exit();
        }
        $i = $i > 0 ? $i - 1 : 0;
        //количество заявок в работе
        $query = $this->db->prepare('SELECT COUNT(id) AS sum_in_job FROM ticket WHERE id_state=550 AND id_montazhnik = '.$id_user.'');
        $query->execute(array($id_user, $id_user));
        $this->result_temp1 = $query->fetchAll(PDO::FETCH_ASSOC);
        $this->result0[$i]["sum_in_job"] = $this->result_temp1[0]["sum_in_job"];
        //количество новых заявок
        $query = $this->db->prepare('SELECT COUNT(id) AS sum_new FROM ticket WHERE id_state=500 AND id_montazhnik = '.$id_user.'');
        $query->execute(array($id_user, $id_user));
        $this->result_temp1 = $query->fetchAll(PDO::FETCH_ASSOC);
        $this->result0[$i]["sum_new"] = $this->result_temp1[0]["sum_new"];
        //количество просроченных
        $query = $this->db->prepare('SELECT COUNT(id) AS finish_time FROM ticket WHERE (id_state<570) AND (id_state>=500) AND (DATE(date_meeting) < DATE(NOW())) AND (date_meeting IS NOT NULL) AND id_montazhnik = '.$id_user.'');
        $query->execute(array($id_user, $id_user));
        $this->result_temp1 = $query->fetchAll(PDO::FETCH_ASSOC);
        $this->result0[$i]["finish_time"] = $this->result_temp1[0]["finish_time"] > 0 ? $this->result_temp1[0]["finish_time"] : 0;
    }
/* ====================================================================
 * Обрезка адресов (убираем индекс, город, область, страну)
 */
    public function Cut_Address($address) {
        $address = preg_replace('/г{0,2}\.{0,1}\s?Ульяновск?\s?,/', "", $address);
        $address = preg_replace('/Россия(,|\s)/', "", $address);
        $address = preg_replace('/\s?Ульяновская область?\s?/', "", $address);
        $address = preg_replace('/\s?Ульяновская обл.?\s?/', "", $address);
        $address = preg_replace('/обл{0,2}\.{0,1}\s?Ульяновская?\s?,/', "", $address);
        $address = preg_replace('/432[0-9]{3}(\s|,\s|,)/', "", $address);

        return $cut_address = $address;
    }
/* ====================================================================
* Проверка авторизации пользователя
*/
    public function Check_Auth_User() {
        $this->result0[0] = array('login' => $_SESSION["id_user"]);
    }
/* ====================================================================
 * Добавление сообщений по тикету
 */
    public function Action_Ticket($action, $id_ticket, $message) {
        $date_time = date('Y-m-d H:i:s');
        switch ($action) {
            case "to_work_montazhnik":
                $id_state = 550;
                $this->Insert_Message_Ticket($id_ticket, $date_time, $_SESSION["id_user"], $id_state, $message);

                $this->Column_Ticket_Update($id_ticket, "id_state", $id_state);
                $this->Column_Ticket_Update($id_ticket, "date_last_update", $date_time);
                $this->Column_Ticket_Update($id_ticket, "user_last_update", $_SESSION["id_user"]);
                break;
            case "finish_work_montazhnik":
                $id_state = 570;
                $this->Insert_Message_Ticket($id_ticket, $date_time, $_SESSION["id_user"], $id_state, $message);

                $this->Column_Ticket_Update($id_ticket, "id_state", $id_state);
                $this->Column_Ticket_Update($id_ticket, "date_last_update", $date_time);
                $this->Column_Ticket_Update($id_ticket, "user_last_update", $_SESSION["id_user"]);
                break;
        }
        $this->result0 = true;
    }
/* ====================================================================
 * Добавление сообщения по тикету
 */
    public function Insert_Message_Ticket($id_ticket, $date, $author, $id_state, $message) {
        try {
            $query = $this->db->prepare('INSERT INTO ticket_message (`id_ticket`, `id_first_ticket`, `date_message`, `author`, `message_text`, `id_state`) VALUES (?, ?, ?, ?, ?, ?)');
            $query->execute(array($id_ticket, $id_ticket, $date, $author, $message, $id_state));

            //записываем в лог информацию
            $date_time = date('Y-m-d H:i:s'); // дата и время авторизации
            $action = "Пользователь добавил сообщение в тикет: id тикета в базе " . $id_ticket;
            $this->insert_row_log($_SESSION["id_user"], $action, $date_time);
        } catch (PDOException $e) {
            echo 'Error : ' . $e->getMessage();
            exit();
        }
    }
/* ====================================================================
 * Обновление тикета в базе
 */
    public function Column_Ticket_Update($id_ticket, $column_name, $value) {
        try {
            $query = $this->db->prepare('UPDATE ticket SET ' . $column_name . '=? WHERE id=?');
            $query->execute(array($value, $id_ticket));
        } catch (PDOException $e) {
            echo 'Error : ' . $e->getMessage();
            exit();
        }
    }
/* ====================================================================
 * Функция возврата результата
 */
    public function getResult() {
        return $this->result0;
    }
}
?>