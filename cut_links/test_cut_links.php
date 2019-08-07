<style>
    body{
        background: linear-gradient(to top, #ffffff, #7b13d3);
    }
    span {
        text-align: center;
        margin-top: 10%;
        display: block;
    }
    #cut_link{
        margin: 0 auto;
        font-weight: bold;
        font-size: 18px;
        cursor: pointer;
        color: white;
        text-decoration: unset;
    }
    h1{
        margin-bottom: 20px;
        display: block;
        color: white;
    }
    #back{
        margin: 0 auto;
        margin-top: 20px;
        display: block;
        padding: 5px 10px;
        width: 100px;
        font-size: 18px;
        cursor: pointer;
        border: 1px solid transparent;
        border-radius: 4px;
        color: white;
        background-color: #35aa47;
        text-decoration: unset;
    }
</style>
<?php
    header('Content-Type: text/html; charset= utf-8');
    
    require 'sql/sql.php';
    $db = connect_db();
    $h = "QqWwEeRrTtYyUuIiOoPpAaSsDdFfGgHhJjKkLlZzXxCcVvBbNnMm1234567890";
    $rand = substr(str_shuffle($h), 0, 5);
    $site = "http://api.evo73.ru/";
    $url = $_POST['url'];
    $siterand = "".$site."".$rand."";

    if ($_POST['submit']) {
        $check_url_query = $db->query("SELECT cut_link FROM test_cut_links WHERE original_link = '".$url."' limit 1");
        $check_url_res = $check_url_query->fetchAll(PDO::FETCH_ASSOC);
        if (isset($check_url_res[0]['cut_link'])){
            echo "<span>".
                    "<h1>Ваша ссылка:</h1><br>".
                    "<a id='cut_link' href='".$check_url_res[0]['cut_link']."' target='_blank'>".$check_url_res[0]['cut_link']."</a><br>".
                    "<a id='back' href='test_con.php'>Ещё раз</a>".
                 "</span>";
        }else{
            $check_url_query = $db->query("INSERT INTO test_cut_links (`original_link`, `cut_link`) VALUES ('".$url."', '".$siterand."')");
            $f = fopen("links/$rand.php", "w");
            fwrite($f, "<?php header('Location: $url') ?>");
            fclose($f);
            $fh = fopen(".htaccess", "a");
            fwrite($fh, "
            RewriteRule ^$rand$ /links/$rand.php");//Перенос сделан специально
            fclose($fh);
            echo "<span>".
                    "<h1>Ваша ссылка:</h1><br>".
                    "<a id='cut_link' href='$siterand' target='_blank'>$siterand</a><br>".
                    "<a id='back' href='test_con.php'>Ещё раз</a>".
                 "</span>";
        }
    }
?>
