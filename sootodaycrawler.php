<?php
	/* Created by Daniel Harasymiw
	 * July 25, 2015
	 * Called by cron job and crawls sootoday.com grabbing the local headlines and 
	 * storing their titles, date, and content in MySQL database
	 */
	include_once('simple_html_dom.php');  
	
	//Database settings
	$servername = 'localhost';
	$username = 'root';
	$password = 'ps42wwS';
	
	//define directory for images to be stored
	define('DIRECTORY', 'images');
	
	//set the default timezone (used for local2 as their date posted format is different and needs to be converted
	//and otherwise php uses a default timezone
	date_default_timezone_set('America/Toronto');
	
	//Create the connection
	$conn = new mysqli($servername, $username, $password);

	//Check the connection
	if ($conn->connect_error) {
	    echo 'Connection failed!';
	} 
	echo 'Connected successfully to database' . '<br/>';
	
	//Select the database for use
	mysqli_query($conn, 'USE localnews');
	
	//get the web page contents
	$target_url = 'http://www.sootoday.com/';
	$html = new simple_html_dom();
	$html->load_file($target_url);
	
	//local news are stored in first occurrence of div class "inside clearfix"
	$local_news_div = $html->find('div[class=inside clearfix]', 0);
	
	//find all of the links in local news and reverse their order so that the most recent links have a higher id
	$local_news_array = array_reverse($local_news_div->find('a'));
	foreach($local_news_array as $news_title){
		$website = ''; //used to keep track of which website uploaded the article, currently only sites are sootoday and local2

		$news_title_untagged = str_replace(array('<b>', '</b>'), '', $news_title->innertext); //remove any tags from title
		
		//check if story is already in database, if it isn't add it
		$sql = 'SELECT * FROM news_article WHERE story_title = "' . $news_title_untagged . '"';
		
		$result = mysqli_query($conn, $sql);
		if (mysqli_num_rows($result) == 0){ //if no rows returned, story hasn't been stored yet
			echo '<b>'.$news_title_untagged. '</b><br/>';
		
			//Determine if news story is from sootoday or local2
			$news_story_url = $news_title->href;
			if ($news_story_url[0] == '/'){ //if story is from sootoday
				$website = 'sootoday';
				$news_story_url = 'http://www.sootoday.com/' . $news_story_url; //attach sootoday url to relative address
				
				//load the news story's web page
				$news_story_html = new simple_html_dom();
				$news_story_html->load_file($news_story_url);

				//get the date of the article
				$date_span = $news_story_html->find('span[class=content-written-by]', 0);

				$date_string = $date_span->innertext;
				$index_of_by = strpos($date_string, 'by') - 11; //get the position of the word by, sub 11 to remove spaces
				
				$date_string = substr($date_string, 0, $index_of_by);
				
				//get the content of the article
				$content_div = $news_story_html->find('div[class=content]', 0);
				
				$counter = 0;//used to keep track of what paragraph we are at because first paragraph contains picture
				$content = '';
				
				//find all of the images at the top of the article
				foreach ($content_div->find('img') as $relative_img_url){
					//Create id number for picture and store that with url in pictures table
					$img_url = 'http://www.sootoday.com/' . $relative_img_url->src;
					save_picture($conn, $news_title_untagged, $img_url);
					
					$relative_img_url->outertext = ''; //remove picture
				}
						
				foreach ($content_div->find('h1') as $element){
					$element->outertext = '';
				}
				foreach ($content_div->find('span') as $element){
					$element->outertext = '';
				}
				foreach ($content_div->find('ul') as $element){
					$element->outertext = '';
				}

				//add the paragraph to the content
				$content = $content_div->innertext;
			}
			else if (strpos($news_story_url, 'local2') !== false){
				$website = 'local2';

				//load the news story's web page
				$news_story_html = new simple_html_dom();
				$news_story_html->load_file($news_story_url);

				//get the date
				$date = $news_story_html->find('time.timeago', 0)->getAttribute('datetime');
				
				//convert the date to the same format as sootoday (day of the week, month, day, year)
				$date = date_create($date);
				$date_string = date_format($date, 'l, F jS, Y');
				
				$img_url = '';
				$content = '';
				//picture and content is stored in meta content
				foreach ($news_story_html->find('meta') as $meta){
					$property = $meta->getAttribute('property');
					if  ($property == 'og:image'){
						$img_url = $meta->getAttribute('content');
					}
					else if ($property == 'og:description') {
						$content = $meta->getAttribute('content');
					}
				}

				//insert the image
				save_picture($conn, $news_title_untagged, $img_url);

				//save the url to the video
				$sql = 'INSERT INTO local2_videos VALUES ("' . $news_title_untagged . '", "' . $news_story_url . '")';
				mysqli_query($conn, $sql);
			}

			//prepare the sql statement so that any ' and " that might be found in article don't break out of sql statement
			$sql = $conn->prepare('INSERT INTO news_article VALUES (NULL, ?, ?, ?, ?)');
			$sql->bind_param('ssss', $news_title_untagged, $date_string, $content, $website);
			$sql->execute();
			echo mysqli_error($conn);
		}
	}
?>

<!-- functions -->
<?php
	//grabs the picture from the website and saves it
	function save_picture($conn, $story_title, $img_url){
		$imgSQL = 'INSERT INTO Pictures VALUES("'. $story_title . '", NULL)';
		mysqli_query($conn, $imgSQL);
		echo mysqli_error($conn);
		$picID = mysqli_insert_id($conn);
		//Get the picture and copy it into images folder and name it based on its ID in database
		$picture = file_get_contents($img_url);
		file_put_contents(DIRECTORY . '/' . $picID . '.jpg', $picture);
	}
?>
