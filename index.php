<?php
include("includes/init.php");

// declare the current location, utilized in header.php
$current_page_id="index";

// create db
$db= new PDO('sqlite:vinyls.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// function to execute query for both add and search
function exec_sql_query($db, $sql, $params) {
  $query = $db->prepare($sql);
  if ($query and $query->execute($params)) {
    return $query;
  }
  return NULL;
}

// associative array of
const SEARCH_FIELDS = [
  "album" => "By Album Name",
  "artist" => "By Artist",
  "year" => "By Year",
  "genre" => "By Genre",
  "tracks" => "By Number of Tracks",
  "label" => "By Record Label"
];

// search form
if (isset($_GET['search']) and isset($_GET['category'])) {
  $do_search= TRUE;
  $category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_STRING);
  $keys= array_keys(SEARCH_FIELDS);
  if (in_array($category, $keys)){
    $search_field= $category;
  } else {
    $search_field= NULL;
    $do_search= FALSE;
  }
  // validate or sanitize (based off of category selection)
  if ($category== 'tracks' || $category== 'year'){
    $search = filter_input(INPUT_GET, 'search', FILTER_VALIDATE_INT);
    $search = trim($search);
  }
  // check that remaining inputs are strings
  if (is_string($_GET['search'])) {
    $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);
    $search = trim($search);
  } else {
    $do_search= FALSE;
    $category= NULL;
    $search= NULL;
  }
} else {
  $do_search= FALSE;
  $category= NULL;
  $search= NULL;
}

// funciton to print all records in a table
function print_table($records){
  foreach($records as $v){
    echo '<tr> <td>'.$v["album"].'</td>
      <td>'.$v["artist"].'</td> <td>'.$v["year"].'</td>
      <td>'.$v["genre"].'</td> <td>'.$v["tracks"].'</td>
      <td>'.$v["label"].'</td> </tr> '; }
}

// finds distinct album names in vinyls table
$vinyls = exec_sql_query($db, "SELECT DISTINCT album FROM vinyls", NULL)->fetchAll(PDO::FETCH_COLUMN);

// add form
if (isset($_POST["submit_insert"])) {
  $album = filter_input(INPUT_POST, 'album', FILTER_SANITIZE_STRING);
  $album= trim($album);
  $artist = filter_input(INPUT_POST, 'artist', FILTER_SANITIZE_STRING);
  $artist= trim($artist);
  $year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT);
  $year= trim($year);
  $genre = filter_input(INPUT_POST, 'genre', FILTER_SANITIZE_STRING);
  $tracks= filter_input(INPUT_POST, 'tracks', FILTER_VALIDATE_INT);
  $tracks= trim($tracks);
  $label = filter_input(INPUT_POST, 'label', FILTER_SANITIZE_STRING);
  $label= trim($label);
  $invalid= FALSE;
  $exists= FALSE;

// check for existence in the database AND validity
  foreach ($vinyls as $vinyl) {
    if (strcasecmp($album, $vinyl)==0) {
      $exists= TRUE;
    }
  }
  if ($tracks < 0 or $tracks > 40) {
    $invalid= TRUE;
  }
  if ($year < 1900 or $year >2018) {
    $invalid= TRUE;
  }

// if it is valid and does not exist, add the entry
  if (!$exists && !$invalid){
    $sql= "INSERT INTO vinyls (album, artist, year, genre, tracks, label) VALUES (:album, :artist, :year, :genre, :tracks, :label);";
    $params = array(':album' => $album, ':artist' => $artist, ':year'=> $year, ':genre'=> $genre, ':tracks'=> $tracks, ':label'=> $label);
    $result = exec_sql_query($db, $sql, $params);
  }
}
?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" type="text/css" href="styles/all.css" media="all" />
  <title>Home - <?php echo $title;?></title>
</head>

<body>
<?php include("includes/header.php");?>
<br>
<div class="row">
  <div class="column left">
    <h2>Search</h2>
    <form id="searchForm" action="index.php" method="get">
      <select name="category">
        <option value="" selected disabled>Search By</option>
        <?php
        // add all search fields as options
        foreach(SEARCH_FIELDS as $field_name => $display){
          ?>
          <option value="<?php echo $field_name;?>"><?php echo $display;?></option>
          <?php
        }
        ?>
      </select>
      <input type="text" name="search"/>
      <button type="submit">Search</button>
    </form>

  </div>
  <div class="column center">
    <?php
    if ($do_search) {
      // print heading for search results
    ?>
      <h2>Results</h2>
    <?php
      // return by results
      $sql = "SELECT * FROM vinyls WHERE $search_field LIKE '%' || :search || '%'";
      $params = array(':search' => $search);
      $records = exec_sql_query($db, $sql, $params)->fetchAll();
    } elseif(isset($_POST["submit_insert"]) && $exists) {
      // case: tried to add duplicate, go back to home
      echo "<h2> Vinyl already exists! </h2>
      <form action='index.php'> <button type='submit'> Go back </button> </form>";
      $records = array();
    } elseif(isset($_POST["submit_insert"]) && $invalid) {
      // case: bad submission, go back to home
      echo "<h2> Invalid vinyl submission! </h2>
      <form action='index.php'> <button type='submit'> Go back </button> </form>";
      $records = array();
    } else {
      // case: added new song successfully
      if (isset($_POST["submit_insert"])) {
        echo "<h2> Added vinyl!</h2>";
      }
      // query all results with new addition
      $sql = "SELECT * FROM vinyls";
      $params = array();
      $records = exec_sql_query($db, $sql, $params)->fetchAll();
    }

    // print search results
    if (isset($records)) {
      // print if there are no records
      if (!empty($records)){
        echo "<h2> All vinyls </h2>";
        echo "<table>
        <tr> <th>Album Name</th> <th>Artist</th> <th>Year Released</th>
        <th>Genre</th> <th>Number of Tracks</th> <th>Record Label </th>
        </tr>";
        print_table($records);
        echo "</table>";
      } else {
        // case: invalid search and records is empty
        echo "<h2> No vinyls! </h2>
          <form action='index.php'> <button type='submit'> Go back </button> </form>";
      }
    }
    ?>
  </div>

  <div class="column right">
    <h2>Add to the Collection!</h2>
    <form method="post">
      Album Name:<br> <input type="text" name="album" required> <br>
      Artist:<br> <input type="text" name="artist" required> <br>
      Year: <br> <input type="number" name="year" required> <br>
      Genre: <br>
      <select name="genre" required>
        <option value="Rap">Rap</option>
        <option value="Rock">Rock</option>
        <option value="Country">Country</option>
        <option value="Pop">Pop</option>
        <option value="Indie">Indie</option>
        <option value="Jazz"> Jazz </option>
      </select> <br>
      Number of Tracks: <br> <input type="number" name="tracks" required> <br>
      Record Label: <br> <input type="text" name="label" required> <br> <br>
      <button type="submit" name="submit_insert"/>Add Vinyl </button> <br>
      </form>
  </div>
</div>
</body>
<?php include("includes/footer.php");?>
</html>
