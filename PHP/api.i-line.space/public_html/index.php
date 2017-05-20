<?php
session_start();
error_reporting(E_ERROR);
ini_set('display_errors', 0);
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
        trigger_error('GMP or BCMATH extension not installed!', E_USER_ERROR); // this will never return
    }
}

function getData($IP, $IPversion) {
	require_once "./dbaccess.php";

	if ($IPversion == 4){
		$select = "select distinct(IPV4_SERVER_NAME), IPV4_MASK, IPV4_TLD, IPV4_CCTLD from IPV4_ILINES where IPV4_NETWORK_START <= ". $IP . " and IPV4_NETWORK_STOP >= " . $IP ." group by IPV4_SERVER_NAME order by IPV4_CCTLD,IPV4_TLD;";
		$rowNameServer =  "IPV4_SERVER_NAME";
		$rowNameMask =  "IPV4_MASK";
	}
	elseif ($IPversion == 6){
		$select = "select distinct(IPV6_SERVER_NAME), IPV6_MASK, IPV6_TLD, IPV6_CCTLD from IPV6_ILINES where IPV6_NETWORK_START <= ". $IP . " and IPV6_NETWORK_STOP >= " . $IP ." group by IPV6_SERVER_NAME order by IPV6_CCTLD, IPV6_TLD;";
		$rowNameServer =  "IPV6_SERVER_NAME";
	}
	else{		
		echo "Something went wrong..."; // this can return alone
	}

		$connection = @new mysqli($host, $db_user, $db_password, $db_name);
		
		if($connection->connect_errno!=0){
			
			echo "Something went wrong..."; // this can return alone
		}
		else{
			
			mysqli_query($connection,"UPDATE STATS SET STATS_QUERY=STATS_QUERY+1;");
						
			if($stid = @$connection->query("$select")){
			
			$row_cnt = $stid->num_rows;

				echo "<SERVER>";
				
				$i = 0;
				$found_row = false;
				
				while ($row = mysqli_fetch_array($stid, MYSQLI_ASSOC)){
					$i++;
					$SERVER_NAME =  $row["$rowNameServer"];
					$found_row = true;
					
					if ($row_cnt == $i){
						echo "$SERVER_NAME";
					}
					else{
						echo "$SERVER_NAME <> ";
					}
				}
				if ($found_row == false) {
					echo "No results";
				}
				echo "</SERVER>";
			}	
	}
	$connection->close();
}

if(isset($_GET['q'])) {
	
	$IP = $_GET['q'];
	$IP = htmlentities($IP, ENT_QUOTES, "UTF-8");
	$IP = preg_replace('/\s+/', '', $IP);

		if(filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)){
			
			if(preg_match('/^::*|^2000\:*|^FC00\:*|^FE80\:*|^FF00\:*|^::ffff:0:0\w*$|^100\:*|^64:ff9b\:*/i', $IP)){
				echo "<SERVER>Not a public IP address</SERVER>"; // this can return alone
			}else{				
				$IPv6 = ip2long_v6($IP);
				getData($IPv6, 6);
			}
		}
		elseif(filter_var($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)){
			
			if(preg_match('/^127\.\d{1,3}\.\d{1,3}\.\d{1,3}$|^128\.\d{1,3}\.\d{1,3}\.\d{1,3}$|^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$|^192.168\.\d{1,3}\.\d{1,3}$|^255\.\d{1,3}\.\d{1,3}\.\d{1,3}$|^0\.\d{1,3}\.\d{1,3}\.\d{1,3}$|^169.254\.\d{1,3}\.\d{1,3}$|^224\.\d{1,3}\.\d{1,3}\.\d{1,3}$|^240\.\d{1,3}\.\d{1,3}\.\d{1,3}$|^172.(16|17|18|19|20|21|22|23|24|25|26|27|28|29|30|31)\.\d{1,3}\.\d{1,3}$|^169.254\.\d{1,3}\.\d{1,3}$|^100.(6(4|5|6|7|8|9)|((7|8|9)[0-9])|(1(0|1)[0-9])|12(0|1|2|3|4|5|6|7))\.\d{1,3}\.\d{1,3}$|^192.88.99.\d{1,3}$|^198.18\.\d{1,3}\.\d{1,3}$|^198.51.100.\d{1,3}$|^203.0.113.\d{1,3}$/i', $IP)){
				echo "<SERVER>Not a public IP address</SERVER>"; // this can return alone
			}else{

				$IPv4 = ip2long($IP);
				getData($IPv4, 4);	
			}
		}
		else {
			echo "Not an IP(4/6) address"; // this can return alone
		}
}
?>