<?php
	/* Created by Daniel Harasymiw
	 * July 25, 2015
	 * This script is called by ajax from javascript
	 * Grabs local news article content that has already been stored on the database
	 * and prints it to the screen to populate the webpage
	 */
	 
	//Database settings
	$servername = 'localhost';
	$username = 'root';
	$password = 'ps42wwS';
	
	$conn = new mysqli($servername, $username, $password); //connect to the database
	
	//get values from ajax call
	$story_id = $_POST['story_id']; //which story to read in next
	$amount_of_stories = $_POST['amount_of_stories']; //how many stories to read in
	
	// Check connection
	if ($conn->connect_error) {
	    echo 'Connection failed!';
	}
	else {
		//grab content from the database
		mysqli_query($conn, 'USE localnews');
		
		//find what the largest id is (most recent news article)
		$sql = 'SELECT ID from news_article ORDER BY ID DESC LIMIT 1';
		$result = mysqli_query($conn, $sql);
		$row = mysqli_fetch_row($result);
		$max_id = $row[0];			
		
		$story_id = $max_id - $story_id;


		//select stories with ids starting from story_id and ending at story_id - amount_of_stories
		$sql = 'SELECT * FROM news_article WHERE ID <= ' . $story_id . 
				' AND ID > ' . ($story_id - $amount_of_stories) .
				' ORDER BY ID DESC';
		$result = mysqli_query($conn, $sql);
		

		if (mysqli_num_rows($result) > 0){
	
			while ($row = mysqli_fetch_assoc($result)){
				//get data from the query
				$story_title = $row['story_title'];
				$story_date = $row['story_date'];
				$content = $row['content'];
				$website = $row['website'];
				//get the image for that story
				$imgSQL = 'SELECT * FROM Pictures WHERE story_title = "' . $story_title. '"';
				$images_array = array();
				$img_results = mysqli_query($conn, $imgSQL);
				echo mysqli_error($conn);
				//get any images that may have been stored for this article and put them in an array
				while ($img_row = mysqli_fetch_assoc($img_results)){
					array_push($images_array, 'images/' . $img_row['ID'] . '.jpg');
				}
				
				//display the data differently depending on what website it came from
				if ($website == 'sootoday'){
					display_sootoday($story_title, $story_date, $content, $images_array);

				}
				else {
					//grab the url of the webpage with the video on it
					$sql = 'SELECT video_url FROM local2_videos WHERE story_title = "' . $story_title . '"';
					$result = mysqli_query($conn, $sql);
					$vid_row = mysqli_fetch_row($result);
					$video_url = $vid_row[0];
					display_local2($story_title, $story_date, $content, $images_array, $video_url);
				}

				
			}
		}
		
	}
	
	
?>

<!-- functions -->
<?php
	//displays the articles that were from sootoday
	function display_sootoday($story_title, $story_date, $content, $images_array){
		//if there is no content to the article
		if (trim(strip_tags($content)) == ""){

			//display the title, date and images of the article
			echo '
			
			<div class="row top-buffer">
				<div class="col-md-6 col-md-offset-5">
					<div class="block title">
						<h3>' . $story_title . '</h3>
						<h4>' . $story_date . '</h4>';
						//display any images that the article has
						foreach ($images_array as $img_url){
							echo '<img src=' . $img_url . ' class="picture_content" height = "50%" width = "50%">';
						}
						echo '
					</div>
				
				</div>					
			
			</div>';
				
		}
		else {
			//display the actual content of the article
			echo '
			
			<div class="row top-buffer">
				
				<div class="col-md-4">
					<div class="block title">
						<h3>' . $story_title . '</h3>
						<h4>' . $story_date . '</h4>';
						
							//add the pictures
							foreach ($images_array as $img_url){
								echo '<img src=' . $img_url . ' class="img-thumbnail">';
							}

					echo '
					</div>
				</div>

				<div class="col-md-8 block">
					<div class="content block"> ' . $content . ' </div>
				</div>

			</div>';
		}

	}
	


	//displays the articles that came from local2
	function display_local2($story_title, $story_date, $content, $images_array, $video_url){

		//display the title and date of the article
		echo '	
	
		<div class="row top-buffer">
			
			<div class="col-md-4">
				<div class="block title">
					<h3>' . $story_title  . '</h3>
					<h4>' . $story_date . '</h4>
				</div>
			</div>

			<div class="col-md-8 block">
				<div class="content block"><p style="text-align: center"';
					
					
					//add in the thumbnail image of the video and turn it into a link to the video's page on local2.ca
					foreach ($images_array as $img_url){
						
						echo '
						<div class="img-rounded video-thumbnail">
						    	<a href="' . $video_url .'"><img src="'. $img_url .'"/></a>
							<div class="video-play-text">
								<a href="' . $video_url . '"style="text-decoration: none; color:#FFFFFF"><h1>Click Here to Watch!</h1></a>
						    	</div>
						    	<p style="text-align: center">' . $content . ' </p>
						</div>';
						

						
					}
					
					
				echo '
				</div>
			</div>
			
		</div>';
	}

?>




