<?php
<?php 
require '../includes/conn.php';

if(isset($_POST['id'])){
    $id = $_POST['id'];
    $sql = "SELECT * FROM instructor WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    
    echo json_encode($row);
}
?>