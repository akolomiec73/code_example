<?php
    header('Content-Type: text/html; charset= utf-8');
?>
<style>
    body{
        background: linear-gradient(to top, #ffffff, #7b13d3);
    }
    form {
        text-align: center;
        margin-top: 10%;
    }
    input[name="url"]{
        font-size: 14px;
        border: 1px solid #e5e5e5;
        padding: 5px 10px;
        width: 250px;
        height: 30px;
    }
    h1{
        margin-bottom: 20px;
        display: block;
        color: white;
    }
    input[name="submit"]{
        padding: 5px 10px;
        font-size: 14px;
        height: 30px;
        cursor: pointer;
        border: 1px solid transparent;
        border-radius: 4px;
        color: white;
        background-color: #35aa47;
    }
</style>
<body>
    <form action="test_cut_links.php" method="post">
        <h1>Сократитель ссылок</h1><br>
        <input type="url" required placeholder="Введите ссылку..." autocomplete="off" name="url">
        <input type="submit" name="submit" value="Сократить">
    </form>
</body>