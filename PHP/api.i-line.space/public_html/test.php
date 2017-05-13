<?php
session_start();
error_reporting(E_ERROR);
ini_set('display_errors', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);

function ip2long_v6($ip) {
    $ip_n = inet_pton($ip);
    $bin = '';
    for ($bit = strlen($ip_n) - 1; $bit >= 0; $bit--) {
        $bin = sprintf('%08b', ord($ip_n[$bit])) . $bin;
    }

    if (function_exists('gmp_init')) {
        return gmp_strval(gmp_init($bin, 2), 10);
    } elseif (function_exists('bcadd')) {
        $dec = '0';
        for ($i = 0; $i < strlen($bin); $i++) {
            $dec = bcmul($dec, '2', 0);
            $dec = bcadd($dec, $bin[$i], 0);
        }
        return $dec;
    } else {
        trigger_error('GMP or BCMATH extension not installed!', E_USER_ERROR);
    }
}

function getData($IP, $IPversion) {
	require_once "./dbaccess.php";

	if ($IPversion == 4){
		$select = "select distinct(IPV4_SERVER_NAME), IPV4_MASK from IPV4_ILINES where IPV4_NETWORK_START <= ". $IP . " and IPV4_NETWORK_STOP >= " . $IP ." group by IPV4_SERVER_NAME order by IPV4_MASK desc;";
		$rowNameServer =  "IPV4_SERVER_NAME";
		$rowNameMask =  "IPV4_MASK";
	}
	elseif ($IPversion == 6){
		$select = "select distinct(IPV6_SERVER_NAME), IPV6_MASK from IPV6_ILINES where IPV6_NETWORK_START <= ". $IP . " and IPV6_NETWORK_STOP >= " . $IP ." group by IPV6_SERVER_NAME order by IPV6_MASK;";
		$rowNameServer =  "IPV6_SERVER_NAME";
		$rowNameMask =  "IPV6_MASK";
	}
	else{		
		echo "DEBUG: jakas lipa <br/>";
	}

		$connection = @new mysqli($host, $db_user, $db_password, $db_name);
		
		if($connection->connect_errno!=0){
			
			echo "Error: " .$connection->connect_errno . "More info: ". $connection->connect_error;
			echo "Something went wrong...";
		}
		else{
			
			mysqli_query($connection,"UPDATE STATS SET STATS_QUERY=STATS_QUERY+1;");
			
			
			if($stid = @$connection->query("$select")){
			
			$row_cnt = $stid->num_rows;

				echo "<SERVER>";
				
				$i = 0;
				
				while ($row = mysqli_fetch_array($stid, MYSQLI_ASSOC)){
				$i++;
				$SERVER_NAME =  $row["$rowNameServer"];
				$MASK = $row["$rowNameMask"];
				
				if ($row_cnt == $i){
					echo "$SERVER_NAME";
				}else{
				echo "$SERVER_NAME <> ";
				}

				}
				echo "</SERVER>";
			}	
	}
	$connection->close();
}

?>

<?php

if(isset($_GET['q'])) {
	
	$IP = $_GET['q'];
	$IP = htmlentities($IP, ENT_QUOTES, "UTF-8");
	$IP = preg_replace('/\s+/', '', $IP);

	echo "IP - $IP <br/>";
	if(preg_match('/^127\.\d{1,3}\.\d{1,3}\.\d{1,3}$|^128\.\d{1,3}\.\d{1,3}\.\d{1,3}$|^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$|^192.168\.\d{1,3}\.\d{1,3}$|^255\.\d{1,3}\.\d{1,3}\.\d{1,3}$|^0\.\d{1,3}\.\d{1,3}\.\d{1,3}$|^169.254\.\d{1,3}\.\d{1,3}$|^224\.\d{1,3}\.\d{1,3}\.\d{1,3}$|^240\.\d{1,3}\.\d{1,3}\.\d{1,3}$/i', $IP)){
		echo "<SERVER>Oh come on... give me some public address</SERVER>";
	}
	if(preg_match('/^::\w*$|^2000::\w*$|^FC00::\w*$|^FE80::\w*$|^FF00::\w*$|^::ffff:0:0\w*$|^100::\w*$|^64:ff9b::\w*$|^2002::\w*$/i', $IP)){
		echo "<SERVER>Oh come on... give me some public address</SERVER>";
	}
	else{
		// IPv6 parser
		if(filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
				
			$IPv6 = ip2long_v6($IP);
			getData($IPv6, 6);
			
		}
		elseif(filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {

			$IPv4 = ip2long($IP);
			getData($IPv4, 4);	
		}
		else {
			echo "Invalid IP, please try again<br/><br/>
			Example:<br/> 
			IPv4: 127.0.0.1 <br/>
			IPv6: 2001:db8:0:2::1<br/>";
		}
	}
}
?>