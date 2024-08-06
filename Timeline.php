<?php
//-----------------------------------------------------------------------------------------
// PDC Tools, Timeline
// By eglobal-eg.com, Sep. 2022
//
// URL: 		https://eglobal-eg.com/api/inquiries/PDC/timeline.php
//
// Database:	"50.62.209.44:3306", "PDC", "PDCEG", "PDC!pw2023"
// Output:		Excel file export (button)
// Note:		Page is refreshed every 30 minutes
//-----------------------------------------------------------------------------------------


include 'db.php';

// Use a more efficient way to parse the query string
$queryParams = [];
parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $queryParams);
//include("auth.php");

//error_reporting(E_ALL);
//ini_set('display_errors', 'on');
ini_set('max_execution_time', '1200'); //600 seconds = 10 minutes
set_time_limit(0);

$dbhost         = 'localhost';
$dbuser         = 'root';
$dbpass         = '';
$dbname             = 'pcds';
$db             = new db($dbhost, $dbuser, $dbpass, $dbname);
$date             = date('Y-m-d');
$filename         = 'PDC_Timeline_' . $date . '.xls';
$debug          = false;
$sql            = '';
$showDetails    = false;
$OutOfService   = false;
$SearchType     = "All";
$Filter         = " and Cancelled is null";
$ShowErrors = [];

if ($OutOfService == true) {
    echo ('<script>window.location ="OutOfService.html";</script>');
}
// decode the message body
$msgBody        = file_get_contents('php://input');
parse_str($_SERVER['QUERY_STRING'], $qry);

$separator      = '%0D%0A';
if (strlen($msgBody) > 11)
    $SearchType = "Bulk";

if (isset($qry['ShowErrors']) == 1)
    $SearchType = "Rejected";

$Title2         = "";
$sortBy         = 7;
if (isset($qry['srcText']) != "") {
    switch ($qry['srcText']) {
        case 'BarCode':
            $Filter = " and BarCode='" . $qry['srcValue'] . "'";
            $Title2 = "Bar code: " . $qry['srcValue'];
            break;

        case 'Tracking':
            $Filter = " and PackageSerial='" . $qry['srcValue'] . "'";
            $Title2 = "Tracking number: " . $qry['srcValue'];
            break;

        case 'Name':
            $Filter = " and Customer_Name like '%" . $qry['srcValue'] . "%'";
            $Title2 = "Customer name contains: " . $qry['srcValue'];
            break;

        case 'Phone':
            $Filter = " and Mobile_No like '%" . $qry['srcValue'] . "%'";
            $Title2 = "Phone number like: " . $qry['srcValue'];
            break;

        case 'Cancelled':
            $Filter = " and Cancelled is not null";
            $Title2 = "Cancelled shipments";
            break;

        case 'Delivered':
            $Filter = " and Delivered between '" . $qry['dr1'] . "' and '" . $qry['dr2'] . "'";
            $Title2 = "Delivered between " . $qry['dr1'] . " and " . $qry['dr2'] . "";
            $sortBy = 11;
            break;

        case 'Returned':
            $Filter = " and DeliveredSF between '" . $qry['dr1'] . "' and '" . $qry['dr2'] . "'";
            $Title2 = "Returned between " . $qry['dr1'] . " and " . $qry['dr2'] . "";
            $sortBy = 13;
            break;

        case 'DeliveryAttempt':
            $Filter = " and DeliveryAttempt like  '%" . $qry['dr1'] . "%'";
            $Title2 = "Delivery attempt on " . $qry['dr1'];
            break;

        case 'ManifestDate':
            $Filter = " and ManifestDate =  '" . $qry['dr1'] . "'";
            $Title2 = "Manifest of " . $qry['dr1'] . " (processed)";
            break;

        case 'LoggedIn':
            $Filter = " and LoggedIn between '" . $qry['dr1'] . "' and '" . $qry['dr2'] . "'";
            $Title2 = "Logged In between " . $qry['dr1'] . " and " . $qry['dr2'] . "";
            $sortBy = 7;
            break;

        case 'undelivered':
            $Filter = " and Delivered is null and DeliveredSF is null and ReturnedToMLH is null and Cancelled is null";
            $Title2 = "Undeliverd shipments (excluding cancelled and returned)";
            $sortBy = 7;
            break;
    }
    //echo("<javascript>alert('".$Filter."');</javascript>");
}

// if($SearchType=="Bulk")
// {
//     $arr            = explode($separator, $msgBody);
//     $arr[0]         = explode("=",$arr[0])[1];
//     $sqlPart        = "in ('";
//     for($item=0;$item<count($arr);$item++)
//     {
//         $sqlPart    .= $arr[$item]."'";
//         if($item<count($arr)-1)
//             $sqlPart .=",'";
//     }
//     $sqlPart .= ")";
//     if($debug==true){
//         echo $sqlPart;
//         echo $msgBody;
//         print_r ($arr);
//     }
// 	$sql 		= "SELECT * from MasterTable where RefreshStatus=0 and ManifestError is null and (BarCode ".$sqlPart." or PackageSerial ".$sqlPart.")";
// }
// else
// {
//     if($SearchType=="Rejected")
//         $sql 		= "SELECT * from MasterTable where ManifestError is not NULL";
//     else
//         $sql 		= "SELECT * from MasterTable where RefreshStatus=0 and ManifestError is null".$Filter;
// }
if ($SearchType == "Bulk") {
    $arr = explode($separator, $msgBody);
    $arr[0] = explode("=", $arr[0])[1];
    $sqlPart = "in ('";
    for ($item = 0; $item < count($arr); $item++) {
        $sqlPart .= $arr[$item] . "'";
        if ($item < count($arr) - 1)
            $sqlPart .= ",'";
    }
    $sqlPart .= ")";

    if ($debug == true) {
        echo $sqlPart;
        echo $msgBody;
        print_r($arr);
    }

    $sql = "SELECT * FROM MasterTable 
                WHERE RefreshStatus = 0 
                AND ManifestError IS NULL 
                AND (
                    (BarCode " . $sqlPart . " OR PackageSerial " . $sqlPart . ")
                )";
} else {
    if ($SearchType == "Rejected")
        $sql = "SELECT * FROM MasterTable WHERE ManifestError IS NOT NULL";
    else
        // Constructing the SQL query with filtering by Delivered
        $sql = "SELECT * FROM MasterTable 
                        WHERE RefreshStatus = 0 
                        AND ManifestError IS NULL 
                        AND (
                            Delivered IS NULL 
                            OR Delivered = CURDATE() 
                            OR Delivered = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                            OR Delivered = DATE_SUB(CURDATE(), INTERVAL 2 DAY)
                        )" . $Filter;
}

//$input    = json_decode($msgBody,true);
$protocol   = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
$full_url   = $protocol . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

$page         = $full_url; //$_SERVER['PHP_SELF'].;
$sec         = "1800";        //Refresh every 30 minutes (30*60=1800)
header("Refresh: $sec; url=$page");

//$ShowPkg 	= $_GET["ShowPkg"];

$results = $db->query($sql)->fetchAll();
//print_r($results);
$data    = array(); {
    foreach ($results as $result) {
        // Added step to consider the collected state as delivered
        // 23.10.2022

        // Had to remove it to allow for clear "undelivered" search
        // 6/3/2023

        //if ($result["CashCollected"] != null && $result["Delivered"] == null && $result["DeliveredSF"] == null)
        //	$result["Delivered"] = $result["CashCollected"];

        // ----------
        if ($SearchType == "Rejected")
            array_push($data, array(
                "ManifestDate"      => $result['ManifestDate'],
                "BarCode"            => $result["BarCode"],
                "Name"                => $result["Customer_Name"],
                "Phone"                => $result["Mobile_No"],
                "PackageSerial"        => $result["PackageSerial"],
                "ManifestError"     => $result["ManifestError"],
                "TrackingNotes"     => $result["TrackingNotes"]
            ));
        else
            array_push($data, array(
                "BarCode"            => $result["BarCode"],
                "Name"                => $result["Customer_Name"],
                "Phone"                => $result["Mobile_No"],
                "PackageSerial"        => $result["PackageSerial"],
                "LatestStatus"      => $result["LatestStatus"],
                "Cancelled"         => $result["Cancelled"],
                "LoggedIn"            => $result["LoggedIn"],
                "InProcess"            => $result["InProcess"],
                "ReadyForDelivery"    => $result["ReadyForDelivery"],
                "DeliveryAttempt"    => $result["DeliveryAttempt"],
                "Delivered"            => $result["Delivered"],
                "ReturnedToMLH"     => $result["ReturnedToMLH"],
                "DeliveredSF"        => $result["DeliveredSF"],
                "TrackingNotes"     => $result["TrackingNotes"]
            ));
    }
    //print_r($data);
}

// Start Web page here

include 'Navigation.php';

?>
<!--<div class="container" id="container">-->
<h3 align="center">
    <?php if ($SearchType == "Rejected")
        echo ('PDC Rejected Shipments');
    else
        echo ('PDC Shipment Timeline'); ?></h3><?php
                                                if ($Title2 != "")
                                                    echo ("<h5 style='text-align: center;'>" . $Title2 . "</h5>");
                                                ?>
<div class="container" style="display: flex; flex-direction: row; margin-left: 0px; width: 100%">
    <div id="bulkform" style="margin: 20px; float: left; display: none;">
        <h2 style="float: left; width: auto;">Bulk search</h2>
        <span style="font-size: 20px; color: Tomato; float: right; margin-top: 18px; border: 1px solid silver; border-radius: 5px; padding: 5px;" style="cursor: pointer;" onmouseover="this.style.background='#ddd'" onmouseout="this.style.background='none'" onClick="document.getElementById('bulkform').style.display = 'none';">
            <i class="fas fa-xmark" title="close"></i></span>
        <p style="margin-top: 69px;">input the list of bar codes or Tracking number, one code per line</p>
        <form name="bulk_form" method="post">
            <div class="form-group">
                <label for="bulksearch">Bar Code list</label>
                <textarea id="bulksearch" name="bulksearch" rows="20" cols="30"></textarea>
                <br>
                <input type="button" value="Submit" style="align-items: center;" onclick="validateBulk();">
            </div>
        </form>
    </div>

    <div class="table-responsive" style="overflow-x: scroll; float: right">
        <div class="well well-sm col-sm-12">
            <b id='project-capacity-count-lable'><?php echo count($data); ?></b> records found.<!-- Updated on <?php date_default_timezone_set("Africa/Cairo");
                                                                                                                echo (date("Y-m-d h:i:sa")); ?>-->
        </div>
        <br />
        <div>
            <table id="TLTable" class="table table-striped table-bordered display" style="font-size: 12px;">
                <thead>
                    <tr>
                        <th>Tracking details</th>
                        <?php if ($SearchType == "Rejected") {
                            echo ('<th>Manifest date</th>');
                        }
                        ?>
                        <th>Package Serial</th>
                        <th>Bar Code</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <?php if ($SearchType == "Rejected") {
                            echo ('<th>Errors</th>');
                        } else {
                            echo ('<th>Latest status             </th>');
                            echo ('<th>Cancelled                 </th>');
                            echo ('<th>Received                  </th>');
                            echo ('<th>In Process                </th>');
                            echo ('<th>Ready For Delivery        </th>');
                            echo ('<th>Delivery Attempts         </th>');
                            echo ('<th>Delivered                 </th>');
                            echo ('<!-- Had to change the header name as we still didnt have a proof of physical delivery to PDC');
                            echo ('<th>Delivered to PDC      </th> -->');
                            echo ('<th>Returned to logistic hub  </th>');
                            echo ('<th>Returned to PDC       </th>');
                            echo ('<th style="display:none;">Tracking</th>');
                        }
                        ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row) : ?>
                        <tr>
                            <td class="dt-control" style="vertical-align: middle;"></td>
                            <?php if ($SearchType == "Rejected") {
                                echo ('<td>' . $row['ManifestDate'] . '</td>');
                            } ?>
                            <td><?php echo $row['PackageSerial']    ?></td>
                            <td><?php echo $row['BarCode']            ?></td>
                            <td><?php echo $row['Name']            ?></td>
                            <td><?php echo $row['Phone']            ?></td>
                            <?php if ($SearchType == "Rejected") {
                                echo ('<td>' . $row['ManifestError'] . '</td>');
                            } else {
                                echo ('<td>' . $row['LatestStatus'] .      '</td>');
                                echo ('<td>' . $row['Cancelled'] .         '</td>');
                                echo ('<td>' . $row['LoggedIn'] .          '</td>');
                                echo ('<td>' . $row['InProcess'] .         '</td>');
                                echo ('<td>' . $row['ReadyForDelivery'] .  '</td>');
                                echo ('<td>' . $row['DeliveryAttempt'] .   '</td>');
                                echo ('<td>' . $row['Delivered'] .         '</td>');
                                echo ('<td>' . $row['ReturnedToMLH'] .     '</td>');
                                echo ('<td>' . $row['DeliveredSF'] .       '</td>');
                                echo ('<td style="display:none;">' . $row['TrackingNotes'] .       '</td>');
                            }
                            ?>
                            <!--<td><button onclick="document.getElementById('tracking').value='<?php echo str_replace("<br>", "\\r\\n", $row['TrackingNotes'])    ?>'; document.getElementById('TrackNotes').style.display='block';">Show details</button></td>-->
                            <!--<button onclick="document.getElementById('tracking').value='2022-12-13T18:45:30 > Item Added on Dispatch (Ramses Traffic Center)\r\n2022-12-13T16:58:38 > Local Dispatch Incoming (Ramses Traffic Center)\r\n2022-12-13T14:35:52 > Item Added on Dispatch (Main Logistic Hub)\r\n2022-12-13T14:18:38 > Item Received from Third Party (Main Logistic Hub)'; document.getElementById('TrackingNotes').style.display='block';">Show details</button>-->
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="TrackNotes" style="margin: 20px; float: left; display: none; width: 600px;">
        <div style="position: fixed; right: 0px;">
            <h2 style="float: left; width: auto;">Tracking details</h2>
            <span style="font-size: 3em; color: Tomato; float: right; margin-top: 18px;">
                <i class="fas fa-xmark" data-fa-transform="shrink-8" style="cursor: pointer;" onmouseover="this.style.background='#ddd'" onmouseout="this.style.background='none'" onClick="document.getElementById('TrackNotes').style.display = 'none';"></i></span>
            <p style="margin-top: 69px;">Tracking details as reported by Egypt Post</p>
            <textarea id="tracking" rows="20" cols="30"></textarea>
        </div>
    </div>

</div>
<!--</div>-->
<script>
    // This script will clear the bulk search form data and will allow the next reload/refresh
    // to load all data from master table.
    // copied as is from https://wordpress.stackexchange.com/questions/96564/how-to-stop-form-resubmission-on-page-refresh#:~:text=Unset%20Form%20Data%20One%20way%20to%20stop%20page,codes%20to%20check%20if%20the%20form%20is%20empty.
    // 2022-12-12

    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }

  // Optimize the validateBulk function
function validateBulk() {
    $input = trim($_POST['bulksearch']);
    $items = explode("\r\n", $input);
    $errors = [];

    foreach ($items as $item) {
        if (!preg_match('/^(ENO[0-9]{10}EG|EG[0-9]{12})$/', $item)) {
            $errors[] = $item;
        }
    }

    if (count($errors) > 0) {
        // Handle errors
    } else {
        // Process valid input
    }
}

    //------------------------------------------------
    // Add Tracking history
    // currently from column 14 from the data query
    //------------------------------------------------
    function format($data) {
    $trackingHistory = $data[14];
    return '<td colspan="5">Tracking history:<br><br>' . $trackingHistory . '</td>';
}

</script>
</body>

<!-- DataTable script, customized to fit the purpose, Sep 2022
	 DataTables.net -->

<script type="text/javascript">
    $(document).ready(function() {
        $('#TLTable thead tr')
            .clone(true)
            .addClass('filters')
            .appendTo('#TLTable thead');

        var table = $('#TLTable').DataTable({
            orderCellsTop: true,
            //fixedHeader: true,
            initComplete: function() {
                var api = this.api();

                // For each column
                api
                    .columns()
                    .eq(0)
                    .each(function(colIdx) {
                        // Set the header cell to contain the input element
                        var cell = $('.filters th').eq(
                            $(api.column(colIdx).header()).index()
                        );
                        var title = $(cell).text();
                        $(cell).html('<input type="text" placeholder="Search" />'); //' + title + '

                        // On every keypress in this input
                        $('input', $('.filters th').eq($(api.column(colIdx).header()).index()))
                            .off('keyup change')
                            .on('keyup change', function(e) {
                                e.stopPropagation();

                                // Get the search value
                                $(this).attr('title', $(this).val());
                                var regexr = '({search})'; //$(this).parents('th').find('select').val();

                                var cursorPosition = this.selectionStart;
                                // Search the column for that value
                                api
                                    .column(colIdx)
                                    .search(
                                        this.value != '' ?
                                        regexr.replace('{search}', '(((' + this.value + ')))') :
                                        '',
                                        this.value != '',
                                        this.value == ''
                                    )
                                    .draw();

                                $(this)
                                    .focus()[0]
                                    .setSelectionRange(cursorPosition, cursorPosition);
                            });
                    });
            },
            pageLength: 25,

            fixedHeader: {
                footer: true
            },
            <?php
            if ($qry['ShowErrors'] == 1)
                echo ("order: [[1, 'desc']],");
            else
                echo ("order: [[" . $sortBy . ", 'desc']],");
            ?>
            dom: 'Bfrtip',
            //dom: '<"dt-top-container"<l><"dt-center-in-div"B><f>r>t<"dt-filter-spacer"f><ip>'
            lengthMenu: [
                [10, 25, 50, -1],
                ['10 rows', '25 rows', '50 rows', 'Show all']
            ],
            buttons: ['pageLength', 'copy', {
                    extend: 'excelHtml5',
                    autoFilter: true,
                    sheetName: 'Exported data'
                }, 'print', {
                    text: 'Bulk search',
                    action: function(e, dt, node, config) {
                        document.getElementById('bulkform').style.display = 'block'
                    }
                },
                <?php if ($qry['ShowErrors'] == 1)
                    echo ('
                {text: "All Shipments",
            action: function ( e, dt, node, config ) {
                window.location = "timeline.php";}}');
                else
                    echo ('
                {text: "Rejected Shipments",
            action: function ( e, dt, node, config ) {
                window.location = "timeline.php?ShowErrors=1";}}');
                ?>
            ]
        });

        // -----------------------------------------------------------
        // When the + button is clicked
        // -----------------------------------------------------------
        $('#TLTable tbody').on('click', 'td.dt-control', function() {

            // read current row and create a new row just after it
            var tr = $(this).closest('tr');
            var row = table.row(tr);

            //alert('click');

            // do the toggleing effect, if the new row is show, hide it and if it is hidden, show it
            if (row.child.isShown()) {
                // This row is already open - close it
                row.child.hide();
                tr.removeClass('shown');
            } else {
                // Open this row, the format function creates a new row with column span of 14, 
                // then copies the data is data element 14 in the data array, which is the tracking notes
                row.child(format(row.data())).show();
                tr.addClass('shown');
            }
        });
    });
</script>
</body>

</html>