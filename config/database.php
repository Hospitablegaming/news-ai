<?php 
class Database{ private $host='localhost'; 
private $db='news_ai'; 
private $user='root'; 
private $pass=''; 
public function connect(){ 
    $conn=new PDO('mysql:host='.$this->host.';dbname='.$this->db,$this->user,$this->pass); 
    $conn->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); 
return $conn; }} 
?>