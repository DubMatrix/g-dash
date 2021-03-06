<?php
//Only allow this script to run from PHP CLI, not from HTTP
if (php_sapi_name() == "cli") {
	require_once(__DIR__.'/../../config/config.php');
	require_once(__DIR__.'/../../lib/settings/settings.php');
	require_once(__DIR__.'/../../lib/functions/functions.php');
	require_once(__DIR__.'/../../lib/EasyGulden/easygulden.php');
	
	//Connect to Gulden
	$gulden = new Gulden(KeyGet($CONFIG, '', 'rpcuser'),KeyGet($CONFIG, '', 'rpcpass'),KeyGet($CONFIG, '127.0.0.1', 'rpchost'),KeyGet($CONFIG, '9232', 'rpcport'));
	
	//Get the latest version info for G-DASH and Gulden
	$latestversionsarray = array();
	$internalip = trim(shell_exec("ip addr | grep 'state UP' -A2 | tail -n1 | awk '{print $2}' | cut -f1 -d'/'"));
	$latestversionsarray = @json_decode(file_get_contents($GDASH['updatecheck']."?ip=".$internalip."&cv=".KeyGet($CONFIG, '0.0', 'dashversion')));
	
	//Write the current Gulden status to the log file
	//GetSystemMemUsage();
	
	//Check if Gulden server is running
	if(KeyGet($CONFIG, '0', 'pushbulletgulden', 'active')=="1") {
		
		//Get the info (last message and current message)
		$lastmessage =KeyGet($CONFIG, '', 'pushbulletgulden', 'lastmess');
		$currentmessage = "";
		if($gulden->getinfo()=="") {
			$currentmessage = "Gulden server is not running!";
		} else {
			$currentmessage = "Gulden server is up and running!";
		}
		
		//Check the last message that was pushed to prevent multiple pushes of the same message
		if($lastmessage!=$currentmessage) {
			
			//The message is different, send a push notification
			$sendpush = shell_exec("curl --header 'Authorization: Bearer ".KeyGet($CONFIG, '', 'pushbullet')."' -X POST https://api.pushbullet.com/v2/pushes --header 'Content-Type: application/json' --data-binary '{\"type\": \"note\", \"title\": \"Gulden Server\", \"body\": \"".$currentmessage."\"}'");
			
			//Set the current message as the last message in the config file
			KeySet($CONFIG, $currentmessage, 'pushbulletgulden', 'lastmes');
			
			//Update the config file
			file_put_contents(__DIR__.'/../../config/config.php', '<?php $CONFIG = '.var_export($CONFIG, true).'; ?>');
		}
	}

	//Check if there is a newer version of Gulden in the repository
	if(KeyGet($CONFIG, '0', 'pushbulletguldenupdate', 'active')=="1") {
		
		//Get the info (last message and current message)
		$lastmessage = KeyGet($CONFIG, '', 'pushbulletguldenupdate', 'lastmes');
		$currentmessage = "";
		$ginfo = $gulden->getinfo();
		$guldenversion = $latestversionsarray->gulden;
		if($ginfo !="") {
			$currentguldenversion = $ginfo['version'];
			if($currentguldenversion < $guldenversion) {
				$currentmessage = "A new version of Gulden is available ($guldenversion).";
				
				//Check the last message that was pushed to prevent multiple pushes of the same message
				if($lastmessage!=$currentmessage) {
					
					//The message is different, send a push notification
					$sendpush = shell_exec("curl --header 'Authorization: Bearer ".KeyGet($CONFIG, '', 'pushbullet')."' -X POST https://api.pushbullet.com/v2/pushes --header 'Content-Type: application/json' --data-binary '{\"type\": \"note\", \"title\": \"Gulden Update\", \"body\": \"".$currentmessage."\"}'");
					
					//Set the current message as the last message in the config file
					KeySet($CONFIG, $currentmessage, 'pushbulletguldenupdate', 'lastmes');
					
					//Update the config file
					file_put_contents(__DIR__.'/../../config/config.php', '<?php $CONFIG = '.var_export($CONFIG, true).'; ?>');
				}
			}
		}
	}

	//Check if there is a new version of G-DASH available
	if(KeyGet($CONFIG, '0', 'pushbulletgdash', 'active')=="1") {
		
		//Get the info (last message and current message)
		$lastmessage = KeyGet($CONFIG, '', 'pushbulletgdash', 'lastmes');
		$currentmessage = "";
		
		//What is the current version of G-DASH
		$currentversion = $GDASH['currentversion'];
		
		//Check which version is the latest version of G-DASH	  
		if(KeyGet($CONFIG, '0', 'updatechannel')=="1") {
			$getlatestversion = $latestversionsarray->beta;
		} else {
			$getlatestversion = $latestversionsarray->stable;
		}
		
		//Set the message
		$currentmessage = "Latest version is ".$getlatestversion.". You are currently running ".$currentversion;
		
		//Check the last message that was pushed to prevent multiple pushes of the same message
		if($getlatestversion > $currentversion && $lastmessage != $currentmessage) {
			
			//The message is different, send a push notification
			$sendpush = shell_exec("curl --header 'Authorization: Bearer ".KeyGet($CONFIG, '', 'pushbullet')."' -X POST https://api.pushbullet.com/v2/pushes --header 'Content-Type: application/json' --data-binary '{\"type\": \"note\", \"title\": \"G-DASH update available\", \"body\": \"".$currentmessage."\"}'");
			
			//Set the current message as the last message in the config file
			KeySet($CONFIG, $currentmessage, 'pushbulletgdash', 'lastmes');
			
			//Update the config file
			file_put_contents(__DIR__.'/../../config/config.php', '<?php $CONFIG = '.var_export($CONFIG, true).'; ?>');
		}
	}

	//Notification if there is a new incoming transaction
	if(KeyGet($CONFIG, '0', 'pushbullettx', 'active')=="1") {
		
		//Create a list of addresses belonging to this wallet
		$addresslistrpc = $gulden->listreceivedbyaddress();
		$addresslist = array_column($addresslistrpc, "address");
		
		//Get the latest transaction for all accounts
		$accounttoshowtx = "*";
		$numoftransactionstoshow = 1;
		$accounttransactions = $gulden->listtransactions($accounttoshowtx, $numoftransactionstoshow);
		
		//List all non-deleted accounts
		$accountlistrpc = $gulden->listaccounts("*", "Normal");
		
		//Get the account name of the last transaction
		$accountname = $accounttransactions[0]['accountlabel'];
		
		//Only get this account from the accountlist array
		$accountlist_thisaccount = selectElementWithValue($accountlistrpc, "label", $accountname);
		
		//Get the type of this account
		$accounttype = $accountlist_thisaccount[0]['type'];
		
		//Check if this is not a witness account
		if($accounttype != "Witness" && $accounttype != "Witness-only witness") 
		{
		
			//Get the raw transaction details
			$transactiondetails = getTransactionDetails($accounttransactions, $numoftransactionstoshow, $addresslist);
			
			//Get only the first item from the function as there is only one to possibly push
			$transactiondetailsitem = $transactiondetails[0];
			
			//Get the amount of Gulden sent/received
			$transactionamount = $transactiondetailsitem['transactionamount'];
			
			//Get the senders address
			$txfromaddress = $transactiondetailsitem['txfromaddress'];
			
			//Get the date and time of the transaction
			$transactiondate = $transactiondetailsitem['transactiondate'];
			
			//Only push a message if it is an incoming transaction
			if($transactionamount > 0) {
				//Get the info (last message and current message)
				$lastmessage = KeyGet($CONFIG, '', 'pushbullettx', 'lastmes');
				$currentmessage = $transactiondate.": $transactionamount Gulden received from $txfromaddress";
				
				//Check the last message that was pushed to prevent multiple pushes of the same message
				if($lastmessage!=$currentmessage) {
					
					//The message is different, send a push notification
					$sendpush = shell_exec("curl --header 'Authorization: Bearer ".KeyGet($CONFIG, '', 'pushbullet')."' -X POST https://api.pushbullet.com/v2/pushes --header 'Content-Type: application/json' --data-binary '{\"type\": \"note\", \"title\": \"Gulden Transaction\", \"body\": \"".$currentmessage."\"}'");
					
					//Set the current message as the last message in the config file
					KeySet($CONFIG, $currentmessage, 'pushbullettx', 'lastmes');
					
					//Update the config file
					file_put_contents(__DIR__.'/../../config/config.php', '<?php $CONFIG = '.var_export($CONFIG, true).'; ?>');
				}
			}
		}
	}

	//Notification if there is a new incoming witness transaction
	if(KeyGet($CONFIG, '0', 'pushbulletwitness', 'active')=="1") {
		
		//Get witness activity
		$mywitnessaccountsnetwork = $gulden->getwitnessinfo("tip", true, true);
		
		//Get all witness accounts
		$mywitnessaccountsnetwork = $mywitnessaccountsnetwork[0]['witness_address_list'];
		
		//Get the current block height
		$ginfo = $gulden->getinfo();
		$currentblock = $ginfo['blocks'];
		
		//Loop through the witness accounts and find the most recent action	
		$lastwitnessactionblock = 0;
		foreach ($mywitnessaccountsnetwork as $witnessdata) {
			if($witnessdata['last_active_block'] > $lastwitnessactionblock) {
				$witnessdetailsname = $witnessdata['ismine_accountname'];
				$lastwitnessactionblock = $witnessdata['last_active_block'];
				$lastwitnessactiondate = date("d/m/Y H:i:s", time() - (($currentblock - $lastwitnessactionblock) / (576 / (24 * 60 * 60))));
			}
		}
		
		//Get the last block that was active in the config
		$lastblock = KeyGet($CONFIG, '0', 'pushbulletwitness', 'lastblock');
		
		//Get the info (last message and current message)
		$lastmessage = KeyGet($CONFIG, '', 'pushbulletwitness', 'lastmes');
		$currentmessage = $lastwitnessactiondate.": New witness action for $witnessdetailsname";
		
		//Check the last message that was pushed to prevent multiple pushes of the same message
		if($lastmessage!=$currentmessage && $lastwitnessactionblock != $lastblock && $witnessdetailsname != "") {
			
			//The message is different, send a push notification
			$sendpush = shell_exec("curl --header 'Authorization: Bearer ".KeyGet($CONFIG, '', 'pushbullet')."' -X POST https://api.pushbullet.com/v2/pushes --header 'Content-Type: application/json' --data-binary '{\"type\": \"note\", \"title\": \"Gulden Witness Action\", \"body\": \"".$currentmessage."\"}'");
			
			//Set the current message as the last message in the config file
			KeySet($CONFIG, $currentmessage, 'pushbulletwitness', 'lastmes');
			KeySet($CONFIG, $lastwitnessactionblock, 'pushbulletwitness', 'lastblock');
			
			//Update the config file
			file_put_contents(__DIR__.'/../../config/config.php', '<?php $CONFIG = '.var_export($CONFIG, true).'; ?>');
		}
	}
}
?>