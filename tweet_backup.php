<?
/*******************************************************************************
   tweet_backup.php
   
   Script for backing up tweets from Twitter locally for archiving and
   searching purposes.
   
   Database schema:
   
   create table tweets(
    id int(11) not null auto_increment,
	tweet_id bigint(20) unsigned not null,
	tweet varchar(140) not null,
	tweet_date datetime not null,
	tweet_source varchar(255) not null,
	tweet_reply_to_id bigint(20) unsigned,
	tweet_reply_to_username varchar(50),
	primary key (id),
	fulltext key tweet (tweet)
   );
   
   Created by Bernie Zimmermann
   January 10, 2009
********************************************************************************/
   
// set up script-wide defines
define('FIRST_TWEET_DATE', 'December 16, 2007');
define('LOCAL_TIMEZONE', 'America/Los_Angeles');
define('TWEET_CONTENT', 'tweet');
define('TWEET_DATE', 'tweet_date');
define('TWEET_ID', 'tweet_id');
define('TWEET_REPLY_TO_ID', 'tweet_reply_to_id');
define('TWEET_REPLY_TO_USERNAME', 'tweet_reply_to_username');
define('TWEET_SOURCE', 'tweet_source');
define('TWITTER_COUNT', 200);
define('TWITTER_TIMELINE_URI', 'http://twitter.com/statuses/user_timeline.xml?screen_name=');
define('TWITTER_USERNAME', 'bernzilla');

// set up database defines
define('DATABASE_HOST', 'localhost');
define('DATABASE_NAME', 'sweetmoolah');
define('DATABASE_PASSWORD', 'pigskinmountains');
define('DATABASE_USERNAME', 'unclerico');

// set the local timezone for all date operations
date_default_timezone_set(LOCAL_TIMEZONE);

// start timing the process
$start = strtotime('now');

// initialize variables
$current_page = 0;
$current_status = 0;
$latest_tweet_date = strtotime(FIRST_TWEET_DATE);
$still_archiving = true;
$tweets = array();

// connect to the database
$connection = @mysqli_connect(DATABASE_HOST, DATABASE_USERNAME, DATABASE_PASSWORD, DATABASE_NAME) or die('Could not connect to MySQL: ' . mysqli_connect_error());

// set the encoding of all forthcoming communication to UTF-8
mysqli_query($connection, "SET NAMES 'utf8'") or die('Could not set the desired encoding: ' . mysqli_error($connection));

// attempt to get the most recent tweet data from the database
$date_sql = 'SELECT ' . TWEET_DATE . ' FROM tweets ORDER BY ' . TWEET_DATE . ' DESC LIMIT 1';
$date_res = mysqli_query($connection, $date_sql) or die('Could not get most recent tweet date from database: ' . mysqli_error($connection));

// get the number of rows returned
$date_rows = mysqli_num_rows($date_res);

// if a row was returned
if (1 == $date_rows)
{
	// fetch the results from the row
	$date_row = mysqli_fetch_assoc($date_res);
	
	// update the latest tweet date with value from query
	$latest_tweet_date = strtotime($date_row[TWEET_DATE]);
}

// archive tweets while there are still tweets to archive
while ($still_archiving)
{
	// increment the current page
	$current_page++;
	
	// build the URI from which to fetch tweets
	$uri = TWITTER_TIMELINE_URI . TWITTER_USERNAME . '&count=' . TWITTER_COUNT . '&page=' . $current_page;

	// fetch the XML from the URI
	$session = curl_init($uri);
	curl_setopt($session, CURLOPT_HEADER, false);
	curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
	$xml = curl_exec($session);
	curl_close($session);
	
	// if the feed can be loaded and parsed
	if ($parsed_xml = simplexml_load_string($xml))
	{
		// if at least one status node exists
		if (isset($parsed_xml->status))
		{
			// iterate through each status returned
			foreach($parsed_xml->status as $status)
			{
				// store the status node's children locally
				$id = $status->id;
				$date = $status->created_at;
				$text = $status->text;
				$source = (isset($status->source)) ? $status->source : '';
				$reply_to_id = (isset($status->in_reply_to_status_id) && !empty($status->in_reply_to_status_id)) ? $status->in_reply_to_status_id : 'NULL';
				$reply_to_username = (isset($status->in_reply_to_screen_name) && !empty($status->in_reply_to_status_id)) ? $status->in_reply_to_screen_name : '';
				
				// if the date is more recent than the most recently archived tweet
				if (strtotime($date) > $latest_tweet_date)
				{				
					// convert the date to a database-friendly date format
					$date = date('Y-m-d H:i:s', strtotime($date));
					
					// store the tweet details in the array of tweets to be archived
					$tweets[$current_status][TWEET_ID] = $id;
					$tweets[$current_status][TWEET_CONTENT] = $text;
					$tweets[$current_status][TWEET_DATE] = $date;
					$tweets[$current_status][TWEET_SOURCE] = $source;
					$tweets[$current_status][TWEET_REPLY_TO_ID] = $reply_to_id;
					$tweets[$current_status][TWEET_REPLY_TO_USERNAME] = $reply_to_username;
					
					// increment the current status counter
					$current_status++;
				}
				
				// else we've hit the most recently archived tweet
				else
				{
					// we are done archiving
					$still_archiving = false;
					
					// break out of the loop
					break;
				}
			}
		}
		
		// else a status node wasn't found
		else
		{
			// this is likely the end of the road, so stop archiving
			$still_archiving = false;
		}
	}
	
	// else the XML content could not be loaded
	else
	{
		die('The XML returned by ' . $uri . ' could either not be loaded or parsed.');
	}
}

// if any tweets were found to archive
if (0 < count($tweets))
{
	// iterate backward through the tweets
	for ($i = ($current_status - 1); $i >= 0; $i--)
	{
		// build a database query for inserting the current tweet
		$tweet_sql = 'INSERT INTO tweets (' . TWEET_ID . ', ' . TWEET_CONTENT . ', ' . TWEET_DATE . ', ' . TWEET_SOURCE . ', ' . TWEET_REPLY_TO_ID . ', ' . TWEET_REPLY_TO_USERNAME . ') VALUES (' . $tweets[$i][TWEET_ID] . ", '" . mysqli_real_escape_string($connection, $tweets[$i][TWEET_CONTENT]) . "', '" . $tweets[$i][TWEET_DATE] . "', '" . mysqli_real_escape_string($connection, $tweets[$i][TWEET_SOURCE]) . "', " . $tweets[$i][TWEET_REPLY_TO_ID] . ", '" . $tweets[$i][TWEET_REPLY_TO_USERNAME] . "')";
		
		// actually insert the tweet data
		mysqli_query($connection, $tweet_sql) or die('SQL query failed: [' . $tweet_sql . '] ' . mysqli_error($connection));
	}
}

// stop timing the process
$finish = strtotime('now');

// calculate the number of seconds the process took
$time = $finish - $start;

// print a summary of the archive process
echo 'Archived ' . count($tweets) . ' tweet(s) in ' . $time . " second(s).\n";
