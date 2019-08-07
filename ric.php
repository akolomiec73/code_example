<?php
//Приём платежей от РИЦ
require ('/var/www/api.evo73.ru/sql/sql.php');

function log_($action,$account,$amount,$pay_id,$pay_date,$code,$message,$bm_code){
    //определение ip
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) { $ip = $_SERVER['HTTP_CLIENT_IP'];} 
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];} 
    else {$ip = $_SERVER['REMOTE_ADDR'];}

    if ($amount !== null){
        $amount	= $amount * 100;
    }	
    $db = connect_db();
    $q = $db -> prepare("INSERT INTO log_payments_ric (action,account,amount,pay_id,pay_date,code,message,bm_code,ip) VALUES (?,?,?,?,?,?,?,?,?)");
    $q -> execute(array($action,$account,$amount,$pay_id,$pay_date,$code,$message,$bm_code,$ip));
}
          
function generate_xml($data){
    $xml = new XmlWriter();
    $xml->openMemory();
    $xml->startDocument('1.0', 'UTF-8');
    $xml->startElement('response');
    function write(XMLWriter $xml, $data){
        foreach($data as $key => $value){
            if(is_array($value)){
                $xml->startElement($key);
                write($xml, $value);
                $xml->endElement();
                continue;
            }
            $xml->writeElement($key, $value);
        }
    }
    write($xml, $data);
    $xml->endElement();
    header('Content-type: text/xml');
    echo $xml->outputMemory(true); 
}

if (isset($_GET['ACTION'])){
    if ($_GET['ACTION'] == 'check'){
        if (isset($_GET['ACCOUNT']) && !empty($_GET['ACCOUNT']) && is_numeric($_GET['ACCOUNT']) || isset($_GET['LOGIN']) && !empty($_GET['LOGIN'])){
            $db_bill = new PDO("oci:dbname=//192.168.7.4/billing" . ';charset=UTF8', 'user', 'pass');//подключение к биллингу
            $db_bill->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            if (isset($_GET['LOGIN']) && !empty($_GET['LOGIN'])){
                $sql_text = "login = '".$_GET['LOGIN']."'";
            }else if (isset($_GET['ACCOUNT']) && !empty($_GET['ACCOUNT']) && is_numeric($_GET['ACCOUNT'])){
                $sql_text = "account_id = ".$_GET['ACCOUNT']."";
            }
            $account_query = $db_bill-> query(" select 
                                                    a.stop_date, 
                                                    a.domain_id,
                                                    a.account_id,
                                                    decode(c.customer_type_id, 1, 0, decode(c.customer_type_id, 2, 1, decode(c.customer_type_id, 4, 1))) customer_type, 
                                                    (select distinct 1 from bm11.services where type_id=50 and status<>'-20' and ".$sql_text." and a.account_id not in(
                                                        SELECT distinct ss.account_id
                                                        FROM bm11.SERVICES ss, bm11.ACCOUNTS aa, bm11.CUSTOMERS_CONTACTS_EXT ccecce
                                                        WHERE ss.account_id = aa.account_id AND ss.customer_id = ccecce.customer_id
                                                        and ss.domain_id = 2 AND ss.status != -20 AND ss.TYPE_id in (14,111) AND ss.account_id IN (SELECT s.account_id FROM bm11.SERVICES s, bm11.CUSTOMERS_CONTACTS_EXT cce
                                                        WHERE  s.customer_id = cce.customer_id and s.domain_id = 2 AND s.status != -20 AND s.TYPE_id = 50 AND cce.customer_type = 0) AND ccecce.customer_type = 0)) as tel
                                                from bm11.accounts a, bm11.customers c
                                                where a.account_id in(select account_id from bm11.services where ".$sql_text.") and a.customer_id=c.customer_id ");
            $account_result = $account_query ->fetchAll(PDO::FETCH_ASSOC);
            
            if ($account_result[0]['TEL'] == 1 || $account_result[0]['CUSTOMER_TYPE'] == 1 ){$tel = 1;}
            else { $tel = 0;}
            
            if (count($account_result) == 1){
                if ($account_result[0]['STOP_DATE'] == null){
                    $code = 0;
                    $message = 'Лицевой счёт актуален';

                    $data = array("CODE" => $code,
                                    "MESSAGE" => $message,
                                    "TEL" => $tel
                                );
                    log_("".$_GET['ACTION']."",$_GET['ACCOUNT'],null,null,null,$code,$message,null,null);	
                    generate_xml($data);  
                }else{
                    $code = 3;
                    $message = 'Обслуживание данного лицевого счёта прекращено';

                    $data = array("CODE" => $code,
                                    "MESSAGE" => $message
                                    );
                    log_($_GET['ACTION'],$_GET['ACCOUNT'],null,null,null,$code,$message,null,null);		
                    generate_xml($data);
                }
            }else{
                $code = 3;
                $message = 'Лицевой счёт не найден';

                $data = array("CODE" => $code,
                                "MESSAGE" => $message
                                );
                log_($_GET['ACTION'],$_GET['ACCOUNT'],null,null,null,$code,$message,null,null);		
                generate_xml($data);
            }
        }else{
            $code = 2;
            $message = 'Пустое значение параметра "ACCOUNT"/"LOGIN"';

            $data = array("CODE" => $code,
                            "MESSAGE" => $message
                            );
            log_($_GET['ACTION'],$_GET['ACCOUNT'],null,null,null,$code,$message,null,null);	
            generate_xml($data);
        }
    }
    else if ($_GET['ACTION'] == 'payment'){//платежи за интернет
        if (isset($_GET['AMOUNT']) && !empty($_GET['AMOUNT']) && is_numeric($_GET['AMOUNT'])){
            $min = 1;
            $max = 15000;
            if ($_GET['AMOUNT'] >= $min && $_GET['AMOUNT'] <= $max){
                if (isset($_GET['PAY_ID']) && !empty($_GET['PAY_ID']) && is_numeric($_GET['PAY_ID'])){
                    if (isset($_GET['PAY_DATE']) && !empty($_GET['PAY_DATE'])){
                        if (isset($_GET['ACCOUNT']) && !empty($_GET['ACCOUNT']) && is_numeric($_GET['ACCOUNT'])){
                            $db_bill = new PDO("oci:dbname=//192.168.7.4/billing" . ';charset=UTF8', 'user', 'pass');//подключение к биллингу
                            $db_bill->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $account_query = $db_bill-> query(" select 
                                                                    a.stop_date, 
                                                                    a.domain_id, 
                                                                    decode(c.customer_type_id, 1, 0, decode(c.customer_type_id, 2, 1, decode(c.customer_type_id, 4, 1))) customer_type, 
                                                                    (select distinct 1 from bm11.services where type_id=50 and status<>'-20' and account_id=".$_GET['ACCOUNT']." and account_id not in(
                                                                        SELECT distinct ss.account_id
                                                                        FROM bm11.SERVICES ss, bm11.ACCOUNTS aa, bm11.CUSTOMERS_CONTACTS_EXT ccecce
                                                                        WHERE ss.account_id = aa.account_id AND ss.customer_id = ccecce.customer_id
                                                                        and ss.domain_id = 2 AND ss.status != -20 AND ss.TYPE_id in (14,111) AND ss.account_id IN (SELECT s.account_id FROM bm11.SERVICES s, bm11.CUSTOMERS_CONTACTS_EXT cce
                                                                        WHERE  s.customer_id = cce.customer_id and s.domain_id = 2 AND s.status != -20 AND s.TYPE_id = 50 AND cce.customer_type = 0) AND ccecce.customer_type = 0)) as tel
                                                                from bm11.accounts a, bm11.customers c
                                                                where a.account_id =".$_GET['ACCOUNT']." and a.customer_id=c.customer_id ");
                            $account_result = $account_query ->fetchAll(PDO::FETCH_ASSOC);
                            if ($account_result[0]['TEL'] == 1 || $account_result[0]['CUSTOMER_TYPE'] == 1 ){$tel = 1;}
                            else { $tel = 0;}
                            if (count($account_result) == 1 && $tel == 0){
                                if ($account_result[0]['STOP_DATE'] == null){
                                    if ($account_result[0]['DOMAIN_ID'] == 2){
                                         $username = 'user';
                                         $password = 'pass';
                                         $REG_DATE = date("d.m.Y_H:i:s");
                                         $txn_date = date(YmdHis);
                                         $PAY_URL='https://nea?command=pay&txn_id='.$_GET['PAY_ID'].'&account='.$_GET['ACCOUNT'].'&sum='.$_GET['AMOUNT'].'&txn_date='.$txn_date;
                                         $PAY = curl_init();
                                         curl_setopt($PAY, CURLOPT_HEADER, false);
                                         curl_setopt($PAY, CURLOPT_USERAGENT, 'EVO LK BOT');
                                         curl_setopt($PAY, CURLOPT_SSL_VERIFYPEER, false);
                                         curl_setopt($PAY, CURLOPT_SSL_VERIFYHOST, false);
                                         curl_setopt($PAY, CURLOPT_URL,$PAY_URL);
                                         curl_setopt($PAY, CURLOPT_TIMEOUT, 30); 
                                         curl_setopt($PAY, CURLOPT_RETURNTRANSFER,1);
                                         curl_setopt($PAY, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
                                         curl_setopt($PAY, CURLOPT_USERPWD, "$username:$password");
                                         $PAY_result=curl_exec($PAY);
                                         curl_close ($PAY);
                                         $p = simplexml_load_string($PAY_result);
                                         if ($p->result == '0'){
                                             $code = 0;
                                             $message = 'Зачисление средств произведено успешно';
                                             $error_code = $p->result;

                                             $data = array("CODE" => $code,
                                                            "MESSAGE" => $message,
                                                            "TEL" => $tel,
                                                            "AMOUNT" => $_GET['AMOUNT']
                                                         );
                                             log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,$error_code);	
                                             generate_xml($data);
                                         }else{
                                             $error_code = $p->result;
                                             switch ($error_code){
                                                 case 241:
                                                     $code = 4;
                                                     $message = 'Сумма слишком мала';
                                                     break;
                                                 case 242:
                                                     $code = 4;
                                                     $message = 'Сумма слишком велика';
                                                     break;
                                                 case 5:
                                                     $code = 3;
                                                     $message = 'Лицевой счёт не найден';
                                                     break;
                                                 case 79:
                                                     $code = 3;
                                                     $message = 'Обслуживание данного лицевого счёта прекращено';
                                                 default:
                                                     $code = -1;
                                                     $message = "Прочая ошибка";
                                             }
                                             $data = array("CODE" => $code,
                                                            "MESSAGE" => $message
                                                             );
                                             log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,$error_code);		
                                             generate_xml($data);	
                                        } 
                                    }else{
                                        $code = 3;
                                        $message = 'Данный лицевой счёт не зарегестрирован в г.Ульяновск';

                                        $data = array("CODE" => $code,
                                                        "MESSAGE" => $message
                                                        );
                                        log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);		
                                        generate_xml($data);
                                    }
                                }else{
                                    $code = 3;
                                    $message = 'Обслуживание данного лицевого счёта прекращено';

                                    $data = array("CODE" => $code,
                                                    "MESSAGE" => $message
                                                    );
                                    log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);		
                                    generate_xml($data);
                                }
                            }else{
                                $code = 3;
                                $message = 'Лицевой счёт не найден или относится к другой услуге';

                                $data = array("CODE" => $code,
                                                "MESSAGE" => $message
                                                );
                                log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);		
                                generate_xml($data);
                            }
                        }else{
                            $code = 2;
                            $message = 'Пустое значение параметра "ACCOUNT"';

                            $data = array("CODE" => $code,
                                            "MESSAGE" => $message
                                            );
                            log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);	
                            generate_xml($data);
                        }
                    }else{
                        $code = 6;
                        $message = 'Неверное значение параметра "PAY_DATE"';

                        $data = array("CODE" => $code,
                                        "MESSAGE" => $message
                                        );
                        log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);	
                        generate_xml($data);
                    }
                }else{
                    $code = 5;
                    $message = 'Неверное значение параметра "PAY_ID"';

                    $data = array("CODE" => $code,
                                    "MESSAGE" => $message
                                    );
                    log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);			
                    generate_xml($data);
                }
            }else{
                $code = 4;
                $message = 'Неверная сумма платежа. Сумма платежа должна быть от '.$min.' до '.$max.' рублей';

                $data = array("CODE" => $code,
                                "MESSAGE" => $message
                                );
                log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);	
                generate_xml($data);
            }
        }else{
            $code = 4;
            $message = 'Неверное значение параметра "AMOUNT"';

            $data = array("CODE" => $code,
                            "MESSAGE" => $message
                            );
            log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);			
            generate_xml($data);
        }
    }
    else if ($_GET['ACTION'] == 'payment_tel'){//платежи за телефонию
        if (isset($_GET['AMOUNT']) && !empty($_GET['AMOUNT']) && is_numeric($_GET['AMOUNT'])){
            $min = 1;
            $max = 15000;
            if ($_GET['AMOUNT'] >= $min && $_GET['AMOUNT'] <= $max){
                if (isset($_GET['PAY_ID']) && !empty($_GET['PAY_ID']) && is_numeric($_GET['PAY_ID'])){
                    if (isset($_GET['PAY_DATE']) && !empty($_GET['PAY_DATE'])){
                        if (isset($_GET['ACCOUNT']) && !empty($_GET['ACCOUNT']) && is_numeric($_GET['ACCOUNT'])){
                            $db_bill = new PDO("oci:dbname=//192.168.7.4/billing" . ';charset=UTF8', 'user', 'pass');//подключение к биллингу
                            $db_bill->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $account_query = $db_bill-> query(" select 
                                                    a.stop_date, 
                                                    a.domain_id, 
                                                    decode(c.customer_type_id, 1, 0, decode(c.customer_type_id, 2, 1, decode(c.customer_type_id, 4, 1))) customer_type, 
                                                    (select distinct 1 from bm11.services where type_id=50 and status<>'-20' and account_id=".$_GET['ACCOUNT']." and account_id not in(
                                                        SELECT distinct ss.account_id
                                                        FROM bm11.SERVICES ss, bm11.ACCOUNTS aa, bm11.CUSTOMERS_CONTACTS_EXT ccecce
                                                        WHERE ss.account_id = aa.account_id AND ss.customer_id = ccecce.customer_id
                                                        and ss.domain_id = 2 AND ss.status != -20 AND ss.TYPE_id in (14,111) AND ss.account_id IN (SELECT s.account_id FROM bm11.SERVICES s, bm11.CUSTOMERS_CONTACTS_EXT cce
                                                        WHERE  s.customer_id = cce.customer_id and s.domain_id = 2 AND s.status != -20 AND s.TYPE_id = 50 AND cce.customer_type = 0) AND ccecce.customer_type = 0)) as tel
                                                from bm11.accounts a, bm11.customers c
                                                where a.account_id =".$_GET['ACCOUNT']." and a.customer_id=c.customer_id ");
                            $account_result = $account_query ->fetchAll(PDO::FETCH_ASSOC);
                            if ($account_result[0]['TEL'] == 1 || $account_result[0]['CUSTOMER_TYPE'] == 1 ){$tel = 1;}
                            else { $tel = 0;}
                            if (count($account_result) == 1 && $tel == 1){
                                if ($account_result[0]['STOP_DATE'] == null){
                                    if ($account_result[0]['DOMAIN_ID'] == 2){
                                        $username = 'user';
                                        $password = 'pass';
                                        $REG_DATE = date("d.m.Y_H:i:s");
                                        $txn_date = date(YmdHis);
                                        $PAY_URL='https://hide?command=pay&txn_id='.$_GET['PAY_ID'].'&account='.$_GET['ACCOUNT'].'&sum='.$_GET['AMOUNT'].'&txn_date='.$txn_date;
                                        $PAY = curl_init();
                                        curl_setopt($PAY, CURLOPT_HEADER, false);
                                        curl_setopt($PAY, CURLOPT_USERAGENT, 'EVO LK BOT');
                                        curl_setopt($PAY, CURLOPT_SSL_VERIFYPEER, false);
                                        curl_setopt($PAY, CURLOPT_SSL_VERIFYHOST, false);
                                        curl_setopt($PAY, CURLOPT_URL,$PAY_URL);
                                        curl_setopt($PAY, CURLOPT_TIMEOUT, 30); 
                                        curl_setopt($PAY, CURLOPT_RETURNTRANSFER,1);
                                        curl_setopt($PAY, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
                                        curl_setopt($PAY, CURLOPT_USERPWD, "$username:$password");
                                        $PAY_result=curl_exec($PAY);
                                        curl_close ($PAY);
                                        $p = simplexml_load_string($PAY_result);
                                        if ($p->result == '0'){
                                            $code = 0;
                                            $message = 'Зачисление средств произведено успешно';
                                            $error_code = $p->result;

                                            $data = array("CODE" => $code,
                                                            "MESSAGE" => $message,
                                                            "TEL" => $tel,
                                                            "AMOUNT" => $_GET['AMOUNT']
                                                        );
                                            log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,$error_code);	
                                            generate_xml($data);
                                        }else{
                                            $error_code = $p->result;
                                            switch ($error_code){
                                                case 241:
                                                    $code = 4;
                                                    $message = 'Сумма слишком мала';
                                                    break;
                                                case 242:
                                                    $code = 4;
                                                    $message = 'Сумма слишком велика';
                                                    break;
                                                case 5:
                                                    $code = 3;
                                                    $message = 'Лицевой счёт не найден';
                                                    break;
                                                case 79:
                                                    $code = 3;
                                                    $message = 'Обслуживание данного лицевого счёта прекращено';
                                                default:
                                                    $code = -1;
                                                    $message = "Прочая ошибка";
                                            }
                                            $data = array("CODE" => $code,
                                                            "MESSAGE" => $message
                                                            );
                                            log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,$error_code);		
                                            generate_xml($data);
                                        }
                                    }else{
                                        $code = 3;
                                        $message = 'Данный лицевой счёт не зарегестрирован в г.Ульяновск';

                                        $data = array("CODE" => $code,
                                                        "MESSAGE" => $message
                                                        );
                                        log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);		
                                        generate_xml($data);
                                    }
                                }else{
                                    $code = 3;
                                    $message = 'Обслуживание данного лицевого счёта прекращено';

                                    $data = array("CODE" => $code,
                                                    "MESSAGE" => $message
                                                    );
                                    log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);		
                                    generate_xml($data);
                                }
                            }else{
                                $code = 3;
                                $message = 'Лицевой счёт не найден или относится к другой услуге';

                                $data = array("CODE" => $code,
                                                "MESSAGE" => $message
                                                );
                                log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);		
                                generate_xml($data);
                            }
                        }else{
                            $code = 2;
                            $message = 'Пустое значение параметра "ACCOUNT"';

                            $data = array("CODE" => $code,
                                            "MESSAGE" => $message
                                            );
                            log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);	
                            generate_xml($data);
                        }
                    }else{
                        $code = 6;
                        $message = 'Неверное значение параметра "PAY_DATE"';

                        $data = array("CODE" => $code,
                                        "MESSAGE" => $message
                                        );
                        log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);	
                        generate_xml($data);
                    }
                }else{
                    $code = 5;
                    $message = 'Неверное значение параметра "PAY_ID"';

                    $data = array("CODE" => $code,
                                    "MESSAGE" => $message
                                    );
                    log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);			
                    generate_xml($data);
                }
            }else{
                $code = 4;
                $message = 'Неверная сумма платежа. Сумма платежа должна быть от '.$min.' до '.$max.' рублей';

                $data = array("CODE" => $code,
                                "MESSAGE" => $message
                                );
                log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);	
                generate_xml($data);
            }
        }else{
            $code = 4;
            $message = 'Неверное значение параметра "AMOUNT"';

            $data = array("CODE" => $code,
                            "MESSAGE" => $message
                            );
            log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);			
            generate_xml($data);
        }
    }
    else if ($_GET['ACTION'] == 'payment_tel_login'){//платёж за телефонию по логину
        if (isset($_GET['AMOUNT']) && !empty($_GET['AMOUNT']) && is_numeric($_GET['AMOUNT'])){
            $min = 1;
            $max = 15000;
            if ($_GET['AMOUNT'] >= $min && $_GET['AMOUNT'] <= $max){
                if (isset($_GET['PAY_ID']) && !empty($_GET['PAY_ID']) && is_numeric($_GET['PAY_ID'])){
                    if (isset($_GET['PAY_DATE']) && !empty($_GET['PAY_DATE'])){
                        if (isset($_GET['LOGIN']) && !empty($_GET['LOGIN'])){
                            $db_bill = new PDO("oci:dbname=//192.168.7.4/billing" . ';charset=UTF8', 'user', 'pass');//подключение к биллингу
                            $db_bill->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $account_query = $db_bill-> query("select 
                                                                    a.stop_date, 
                                                                    a.domain_id,
                                                                    a.account_id,
                                                                    decode(c.customer_type_id, 1, 0, decode(c.customer_type_id, 2, 1, decode(c.customer_type_id, 4, 1))) customer_type, 
                                                                    (select distinct 1 from bm11.services where type_id=50 and status<>'-20' and login = '".$_GET['LOGIN']."' and a.account_id not in(
                                                                        SELECT distinct ss.account_id
                                                                        FROM bm11.SERVICES ss, bm11.ACCOUNTS aa, bm11.CUSTOMERS_CONTACTS_EXT ccecce
                                                                        WHERE ss.account_id = aa.account_id AND ss.customer_id = ccecce.customer_id
                                                                        and ss.domain_id = 2 AND ss.status != -20 AND ss.TYPE_id in (14,111) AND ss.account_id IN (SELECT s.account_id FROM bm11.SERVICES s, bm11.CUSTOMERS_CONTACTS_EXT cce
                                                                        WHERE  s.customer_id = cce.customer_id and s.domain_id = 2 AND s.status != -20 AND s.TYPE_id = 50 AND cce.customer_type = 0) AND ccecce.customer_type = 0)) as tel
                                                                from bm11.accounts a, bm11.customers c
                                                                where a.account_id in(select account_id from bm11.services where login = '".$_GET['LOGIN']."') and a.customer_id=c.customer_id ");
                            $account_result = $account_query ->fetchAll(PDO::FETCH_ASSOC);
                            if ($account_result[0]['TEL'] == 1 || $account_result[0]['CUSTOMER_TYPE'] == 1 ){$tel = 1;}
                            else { $tel = 0;}
                            if (count($account_result) == 1 && $tel == 1){
                                if ($account_result[0]['STOP_DATE'] == null){
                                    if ($account_result[0]['DOMAIN_ID'] == 2){
                                        $username = 'user';
                                        $password = 'pass';
                                        $REG_DATE = date("d.m.Y_H:i:s");
                                        $txn_date = date(YmdHis);
                                        $PAY_URL='https://hide?command=pay&txn_id='.$_GET['PAY_ID'].'&account='.$account_result[0]['ACCOUNT_ID'].'&sum='.$_GET['AMOUNT'].'&txn_date='.$txn_date;
                                        $PAY = curl_init();
                                        curl_setopt($PAY, CURLOPT_HEADER, false);
                                        curl_setopt($PAY, CURLOPT_USERAGENT, 'EVO LK BOT');
                                        curl_setopt($PAY, CURLOPT_SSL_VERIFYPEER, false);
                                        curl_setopt($PAY, CURLOPT_SSL_VERIFYHOST, false);
                                        curl_setopt($PAY, CURLOPT_URL,$PAY_URL);
                                        curl_setopt($PAY, CURLOPT_TIMEOUT, 30); 
                                        curl_setopt($PAY, CURLOPT_RETURNTRANSFER,1);
                                        curl_setopt($PAY, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
                                        curl_setopt($PAY, CURLOPT_USERPWD, "$username:$password");
                                        $PAY_result=curl_exec($PAY);
                                        curl_close ($PAY);
                                        $p = simplexml_load_string($PAY_result);
                                        if ($p->result == '0'){
                                            $code = 0;
                                            $message = 'Зачисление средств произведено успешно';
                                            $error_code = $p->result;

                                            $data = array("CODE" => $code,
                                                            "MESSAGE" => $message,
                                                            "TEL" => $tel,
                                                            "AMOUNT" => $_GET['AMOUNT']
                                                        );
                                            log_($_GET['ACTION'],$_GET['LOGIN'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,$error_code);	
                                            generate_xml($data);
                                        }else{
                                            $error_code = $p->result;
                                            switch ($error_code){
                                                case 241:
                                                    $code = 4;
                                                    $message = 'Сумма слишком мала';
                                                    break;
                                                case 242:
                                                    $code = 4;
                                                    $message = 'Сумма слишком велика';
                                                    break;
                                                case 5:
                                                    $code = 3;
                                                    $message = 'Лицевой счёт не найден';
                                                    break;
                                                case 79:
                                                    $code = 3;
                                                    $message = 'Обслуживание данного лицевого счёта прекращено';
                                                default:
                                                    $code = -1;
                                                    $message = "Прочая ошибка";
                                            }

                                            $data = array("CODE" => $code,
                                                            "MESSAGE" => $message
                                                            );
                                            log_($_GET['ACTION'],$_GET['LOGIN'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,$error_code);		
                                            generate_xml($data);	
                                        }
                                    }else{
                                        $code = 3;
                                        $message = 'Данный лицевой счёт не зарегестрирован в г.Ульяновск';

                                        $data = array("CODE" => $code,
                                                        "MESSAGE" => $message
                                                        );
                                        log_($_GET['ACTION'],$_GET['LOGIN'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);		
                                        generate_xml($data);
                                    }
                                }else{
                                    $code = 3;
                                    $message = 'Обслуживание данного лицевого счёта прекращено';

                                    $data = array("CODE" => $code,
                                                    "MESSAGE" => $message
                                                    );
                                    log_($_GET['ACTION'],$_GET['LOGIN'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);		
                                    generate_xml($data);
                                }
                            }else{
                                $code = 3;
                                $message = 'Лицевой счёт не найден';

                                $data = array("CODE" => $code,
                                                "MESSAGE" => $message
                                        
                                                );
                                log_($_GET['ACTION'],$_GET['LOGIN'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);		
                                generate_xml($data);
                            }
                        }else{
                            $code = 2;
                            $message = 'Пустое значение параметра "LOGIN"';

                            $data = array("CODE" => $code,
                                            "MESSAGE" => $message
                                            );
                            log_($_GET['ACTION'],$_GET['LOGIN'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);	
                            generate_xml($data);
                        }
                    }else{
                        $code = 6;
                        $message = 'Неверное значение параметра "PAY_DATE"';

                        $data = array("CODE" => $code,
                                        "MESSAGE" => $message
                                        );
                        log_($_GET['ACTION'],$_GET['LOGIN'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);	
                        generate_xml($data);
                    }
                }else{
                    $code = 5;
                    $message = 'Неверное значение параметра "PAY_ID"';

                    $data = array("CODE" => $code,
                                    "MESSAGE" => $message
                                    );
                    log_($_GET['ACTION'],$_GET['LOGIN'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);			
                    generate_xml($data);
                }
            }else{
                $code = 4;
                $message = 'Неверная сумма платежа. Сумма платежа должна быть от '.$min.' до '.$max.' рублей';

                $data = array("CODE" => $code,
                                "MESSAGE" => $message
                                );
                log_($_GET['ACTION'],$_GET['LOGIN'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);	
                generate_xml($data);
            }
        }else{
            $code = 4;
            $message = 'Неверное значение параметра "AMOUNT"';

            $data = array("CODE" => $code,
                            "MESSAGE" => $message
                            );
            log_($_GET['ACTION'],$_GET['LOGIN'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);			
            generate_xml($data);
        }
    }else{
        $code = 2;
        $message = 'Неверное значение параметра "ACTION"';

        $data = array("CODE" => $code,
                        "MESSAGE" => $message
                        );
        log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);	
        generate_xml($data);		
    }
}else{
    $code = 2;
    $message = 'Неверный запрос';

    $data = array("CODE" => $code,
                    "MESSAGE" => $message
                );
    log_($_GET['ACTION'],$_GET['ACCOUNT'],$_GET['AMOUNT'],$_GET['PAY_ID'],$_GET['PAY_DATE'],$code,$message,null);
    generate_xml($data);
}
?>