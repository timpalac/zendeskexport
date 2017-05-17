<?php
define("ZDAPIKEY", "ENTER_API_KEY");
define("ZDUSER", "ENTER_EMAIL");
define("ZDURL", "https://ENTER_URL.zendesk.com/api/v2");

function curlWrap($url, $json, $action)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 10 );
	curl_setopt($ch, CURLOPT_URL, ZDURL.$url);
	curl_setopt($ch, CURLOPT_USERPWD, ZDUSER."/token:".ZDAPIKEY);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	switch($action){
		case "POST":
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
			break;
		case "GET":
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
			break;
		case "PUT":
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
			break;
		case "DELETE":
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
			break;
		default:
			break;
	}

	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
	curl_setopt($ch, CURLOPT_USERAGENT, "MozillaXYZ/1.0");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	$output = curl_exec($ch);
	curl_close($ch);
	$decoded = json_decode($output);
	return $decoded;
}

function searchArray($id, $array) {
   foreach ($array as $key => $val) {
       if ($val['id'] === $id) {
           return $val['name'];
       }
   }
   return null;
}

if (isset($_GET['startdate']) && !empty($_GET['startdate'])) {
	// Export Variables
	$pagenum = 1;
	$page = 1;

	$startdate = date_format(new DateTime($_GET['startdate']), 'Y-m-d');
	if (isset($_GET['enddate'])) {
		$enddate = date_format(new DateTime($_GET['enddate']), 'Y-m-d');
	} else {
		$enddate = date('Y-m-d');
	}

	if (isset($_GET['state'])) {
		$state = $_GET['state'];	
	} else {
		$state = 'created';
	}
	$filename = 'tickets_' . $state . '_' . $startdate . '_' . $enddate . '.csv';

	header('Content-type: text/csv');
	header('Content-Disposition: attachment; filename=' . $filename);
	header('Pragma: no-cache');
	header('Expires: 0');

	// File Variables	
	$fh = fopen('php://output', 'w');
	date_default_timezone_set('UTC');
	$time = strtotime("-8 hours");

	// Start Export
	while ($page != null)
	{
	    //query API return to $data
	    if ($state === 'created') {
	    	$url = "/search.json?page=" . $pagenum . "&query=created>=" . $startdate . "%20created<=" . $enddate . "%20type:ticket&sort_by=created_at";
	    } else if ($state==='updated') {
	    	$url = "/search.json?page=" . $pagenum . "&query=updated>=" . $startdate . "%20updated<=" . $enddate . "%20type:ticket&sort_by=updated_at";
	    }

	    $data = curlWrap($url, null, "GET"); 

	    if ($pagenum===1) {
			$keys = array(
				'0' => 'Id',
				'1' => 'Url',
				'2' => 'Created',
				'3' => 'Updated',
				'4' => 'Type',
				'5' => 'Subject',
				'6' => 'Description',
				'7' => 'Priority',
				'8' => 'Status',
				'9' => 'Requester',
				'10' => 'Submitter',
				'11' => 'Assignee',
				'12' => 'Organization',
				'13' => 'Group',
				'14' => 'Tags',
				'15' => 'Comments'
			);
			fputcsv($fh, $keys, ",", "\"");

			// Get All Organizations
			$get_orgs = "/organizations.json";
			$orgs = curlWrap($get_orgs, null, "GET"); 
			$orgs = json_decode(json_encode($orgs->organizations), true);

			// Get All Users
			$get_users = "/users.json";
			$users = curlWrap($get_users, null, "GET"); 
			$users = json_decode(json_encode($users->users), true);

			// Get All Groups
			$get_groups = "/groups.json";
			$groups = curlWrap($get_groups, null, "GET"); 
			$groups = json_decode(json_encode($groups->groups), true);
	    }

		foreach($data->results as $result) { 
			$fc = 0;
			$tc = 0;

			$result = json_decode(json_encode($result), true);

			$tags[] = $result['tags'];

			$result = array(
				'id' => $result['id'],
				'url' => $result['url'],
				'created_at' => $result['created_at'],
				'updated_at' => $result['updated_at'],
				'type' => $result['type'],
				'subject' => $result['subject'],
				'description' => $result['description'],
				'priority' => $result['priority'],
				'status' => $result['status'],
				'requester' => searchArray($result['requester_id'], $users),
				'submitter' => searchArray($result['submitter_id'], $users),
				'assignee' => searchArray($result['assignee_id'], $users),
				'organization' => searchArray($result['organization_id'], $orgs),
				'group' => searchArray($result['group_id'], $groups),
				'tags' => '',
				'comments' => ''
			);

			foreach($tags[$tc] as $tag) {
				if ($tc!==0){
					$result['tags'] .= '|';
				}
				if ($tag!=='') {
					$result['tags'] .= $tag;
				}
				$tc++;
			}

			// Get Additional Comments
			$get_comments = "/tickets/" . $result['id'] . "/comments.json";
			$comments = curlWrap($get_comments, null, "GET"); 
			$comments = json_decode(json_encode($comments->comments), true);
			if ($comments) {
				foreach($comments as $comment) {
					if ($comment['body']){
						if (!$result['comments']) {
							$result['comments'] = $comment['body'];
						} else {
							$result['comments'] .= "\r \r" . $comment['body'];
						}
					}
				}
			}
			unset($tags);
			unset($comments);
			fputcsv($fh, $result, ",", "\"");
		}

	    $page = $data->next_page;
	    if ($page === null) {
	    	fclose($fh);
	    }
	    $pagenum++;
	}

	exit();
}
?>

<!DOCTYPE html>
<html>
<head>
	<title>ZenDesk Ticket Export</title>
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
	<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
	<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
	<script>
	$(function() {
		$("#startdate, #enddate").datepicker();
	});
	</script>
</head>
<body>
<div class="container-fluid">
	<h3>Export from ZenDesk</h3>
	<div class="row">
		<div class="col-sm-6">
			<form method="get" action="" autocomplete="off">
				<div class="form-group">
					<label for="startdate">Select a Start Date</label>
					<input type="text" name="startdate" id="startdate" class="form-control" value="<?php if(isset($_GET['startdate'])) { $_GET['startdate']; } ?>" required>
				</div>
				<div class="form-group">
					<label for="enddate">Select an End Date</label>
					<input type="text" name="enddate" id="enddate" class="form-control" value="<?php if(isset($_GET['enddate'])) { $_GET['enddate']; } ?>">
				</div>
				<div class="form-group">
					<select name="state" id="state" class="form-control">
						<option value="created">Created</option>
						<option value="updated">Updated</option>
					</select>
				</div>
				<p><button type="submit" class="btn btn-default">Generate CSV</button></p>
			</form>
		</div>
	</div>
</div>
</body>
</html>