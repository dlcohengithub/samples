/**********************************************************
Video Pulldowns
  pulldowns for collection diagnotic, locality
  ajax processing
**********************************************************/

var VideoList = [];          // set in doCollectionSelected
var CurrentCollectionId = 0; // set in doCollectionSelected
var CurrentDiagnosticId = 0; // set in doCollectionSelected
var CurrentLocalityId = 0;   // set in doCollectionSelected
var CurrentVideoId = 0;      // set in doCollectionSelected

/**********************************************************
doCollectionSelected: collection selected in pulldown
  ajax processing, with json
  collection_id: collection id
  diagnostic_id: diagnostic id
  locality_id: locality id
  video_id: video id
**********************************************************/
function doCollectionSelected(collection_id, diagnostic_id, locality_id, video_id)
{
  if (arguments.length > 0)
  {
    CurrentCollectionId = makeNumeric(collection_id);
    if (arguments.length > 1)
    {
      CurrentDiagnosticId = makeNumeric(diagnostic_id);
      if (arguments.length > 2)
      {
        CurrentLocalityId = makeNumeric(locality_id);
        if (arguments.length > 3)
        {
          CurrentVideoId = makeNumeric(video_id);
        }
      }
    }
  }
  var url="/select_video.php" + makeNoCache() + '&collection_id=' + $("#collection_id").val();
  $.getJSON(url,'',processCollectionSelected);
}

/**********************************************************
processCollectionSelected: process collection selected in pulldown
  ajax processing, with json
  diagnostic_id: diagnostic id
  locality_id: locality id
  title: video title
**********************************************************/
function processCollectionSelected(data)
{
  //data is array of {diagnostic_id, locality_id, title}
  VideoList = data; // global list of videos for collection
  buildDiagnostic();
}

/**********************************************************
buildDiagnostic: build diagnostic list for videos in collection
**********************************************************/
function buildDiagnostic()
{
  var ix;
  var vals = [];
  var id;
  for(ix = 0; ix < VideoList.length; ix++) // VideoList set in processCollectionSelected
  {
    id = VideoList[ix].diagnostic_id;
    val = VideoList[ix].diagnostic_name;
    if (vals[id] === undefined)
    {
      vals[' '+id] = val; // yuck - otherwise, js sorts as sparse array!
    }
  }
  vals = sortAssociative(vals);

  ix = 0;
  var selected = false;
  var diagnostic_list = $("#diagnostic_id")[0];
  diagnostic_list.options.length = 0;
  for (id in vals)
  {
    trim_val = trimWS(vals[id]);
    trim_id = trimWS(id);
    selected = false;
    if (trim_id == CurrentDiagnosticId) selected = true;
    diagnostic_list.options[ix] = new Option(trim_val, trim_id, false, selected);
    ix += 1;
  }
  doDiagnosticSelected();
}

/**********************************************************
doDiagnosticSelected: diagnostic selected in pulldown
**********************************************************/
function doDiagnosticSelected()
{
  if ($("#right-container").length) $("#right-container").hide();
  
  var diagnostic_id = $("#diagnostic_id").val();
  // extract and sort locality
  var ix;
  var vals = [];
  var id;
  for(ix = 0; ix < VideoList.length; ix++) // VideoList set in processCollectionSelected
  {
    if (VideoList[ix].diagnostic_id != diagnostic_id) continue;
    id = VideoList[ix].locality_id;
    val = VideoList[ix].locality_name;
    if (vals[id] === undefined)
    {
      vals[' '+id] = val; // yuck - otherwise, js sorts as sparse array!
    }
  }
  vals = sortAssociative(vals);

  ix = 0;
  var selected = false;
  var locality_list = $("#locality_id")[0];
  locality_list.options.length = 0;
  for (id in vals)
  {
    trim_val = trimWS(vals[id]);
    trim_id = trimWS(id);
    selected = false;
    if (trim_id == CurrentLocalityId) selected = true;
    locality_list.options[ix] = new Option(trim_val, trim_id, false, selected);
    ix += 1;
  }
  doLocalitySelected();
}

/**********************************************************
doLocalitySelected: locality selected in pulldown
**********************************************************/
function doLocalitySelected()
{
  if ($("#right-container").length) $("#right-container").hide();

  // extract and sort title
  var ix;
  var diagnostic_id = $("#diagnostic_id").val();
  var locality_id = $("#locality_id").val();
  var vals = [];
  var id;
  for(ix = 0; ix < VideoList.length; ix++) // VideoList set in processCollectionSelected
  {
    if (VideoList[ix].diagnostic_id != diagnostic_id) continue;
    if (VideoList[ix].locality_id != locality_id) continue;
    id = VideoList[ix].video_id;
    val = VideoList[ix].title;
    if (vals[id] === undefined)
    {
      vals[' '+id] = val; // yuck - otherwise, js sorts as sparse array!
    }
  }
  vals = sortAssociative(vals);

  ix = 0;
  var selected = false;
  var title_list = $("#title")[0];
  title_list.options.length = 0;
  for (id in vals)
  {
    trim_val = trimWS(vals[id]);
    trim_id = trimWS(id);
    selected = false;
    if (trim_id == CurrentVideoId) selected = true;
    title_list.options[ix] = new Option(trim_val, trim_id, false, selected);
    selected = false;
    ix += 1;
  }
}

/**********************************************************
doTitleSelected: video title selected in pulldown
**********************************************************/
function doTitleSelected()
{
  if ($("#right-container").length) $("#right-container").hide();
}  
