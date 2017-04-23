<?php
/**
 * Created by PhpStorm.
 * User: Lombardo
 * Date: 23/02/17
 * Time: 19:24
 */


$h_file = 'me_at_the_zoo'.".in";
$h_file_content = file_get_contents($h_file);

$h_lines = explode("\n", $h_file_content);
$h_number_of_lines_array = explode(" ", $h_lines[0]);

$h_number_of_lines['videos']= $h_number_of_lines_array[0];
$h_number_of_lines['endpoints']= $h_number_of_lines_array[1];
$h_number_of_lines['request_descriptions']= $h_number_of_lines_array[2];
$h_number_of_lines['caches']= $h_number_of_lines_array[3];
$h_number_of_lines['caches_dimensions']= $h_number_of_lines_array[4];

$h_data['videos'] = explode(" ", $h_lines[1]);
$h_data['endpoints']= Array();
$h_data['request_descriptions']= Array();
$h_data['caches']= Array();
$h_data['caches_dimensions']= Array();

$cache_cache_server = Array();

$final_results = [];
$max_cache_weight = (int)$h_number_of_lines['caches_dimensions'];

$h_lines_counter = 2;

for ($i = 0; $i < $h_number_of_lines['endpoints']; $i++)
{
    $tmp = explode(" ", $h_lines[$h_lines_counter]);

    $endpoint = Array();
    $endpoint['id'] = $i;
    $endpoint['datacenter_latency'] = $tmp[0];
    $endpoint['cache_number'] = $tmp[1];
    $h_lines_counter++;


    $endpoint['caches'] = Array();

    foreach (range(0,$endpoint['cache_number']-1) as $idx)
    {
        $tmp = explode(" ", $h_lines[$h_lines_counter]);
        $cache = Array();
        $cache['id'] = $tmp[0];
        $cache['latency'] = $tmp[1];
        $cache['endpoint'] = $endpoint['id'];
        $endpoint['caches'][] = $cache;
        $h_lines_counter++;
    }

    $h_data['endpoints'][] = $endpoint;

}

echo "1\n";

for ($i = 0; $i < $h_number_of_lines['request_descriptions']; $i++)
{
    $tmp = explode(" ", $h_lines[$h_lines_counter]);

    $request_descriptions = Array();
    $request_descriptions['number_of_requests'] = (int)$tmp[2];
    $request_descriptions['video_number'] = (int)$tmp[0];
    $request_descriptions['endpoint_origin'] = (int)$tmp[1];
    $request_descriptions['weight'] = "aaa"; //$h_data['videos'][(int)$tmp[0]];

    $h_data['request_descriptions'][] = $request_descriptions;

    $h_lines_counter++;
}

echo "2\n";

// end parsing

$o_requests = $h_data['request_descriptions'];
$o_videos = Array();

/*
foreach ($o_requests as $request)
{

    $o_video_data = $o_videos[$request['video_number']];

    if (!$o_video_data)
        $o_video_data = Array();

    $o_video_data['video_number'] = $request['video_number'];
    $o_video_data['endpoint_data'][] = ['number_of_requests' => $request['number_of_requests'], 'endpoint_origin' => $request['endpoint_origin']];
    $o_video_data['best_cache_server'] = get_best_cache_server_for_endpoint($request['endpoint_origin'], $h_data);

    $o_videos[$request['video_number']] = $o_video_data;
}
*/

echo "Number of requests is: ".count($o_requests)."\n";
$counter = 0;
foreach ($o_requests as $request)
{
    echo $counter."\n";
    $counter++;

    $o_video_data = Array();
    $o_video_data['video_number'] = $request['video_number'];
    $o_video_data['endpoint_data'][] = ['number_of_requests' => $request['number_of_requests'], 'endpoint_origin' => $request['endpoint_origin'], 'weight' => get_video_weight($request['video_number'])];
    $o_video_data['best_cache_server'] = get_best_cache_server_for_endpoint($request['endpoint_origin'], $h_data);

    $o_videos[$request['video_number']][] = $o_video_data;

}

echo "3\n";


// now order by the videos with the major number of requests
// come ordinare
// parto dagli endpoint con la maggior datacenter latency
// esploro tutti i loro video
// li ordino per numero di visite
// li aggiungo al cache server più vicino

$endpoints_ordered = get_endpoints_with_major_datacenter_latency($h_data);

echo "Number of endpoints is: ".count($endpoints_ordered)."\n";
$counter = 0;
foreach ($endpoints_ordered as $endpoint)
{
    echo $counter."\n";
    $counter++;
    $videos = get_videos_for_endpoint_ordered($endpoint['id'], $o_videos);

    if ($counter > 12)
    {
        break;
    }
    foreach ($videos as $video)
    {
        $cache_servers = get_cache_server_list_for_endpoint($video['endpoint_origin'], $h_data);

        $result = false;

        $idx = 0;
        while ($result == false && $idx < count($cache_servers))
        {

            $cache_server = $cache_servers[$idx];
            $result = add_to_cache_server($cache_server['id'], $video['video_number']);
            $idx++;
        }

    }
}
echo "4\n";

print_result();


function get_best_cache_server_for_endpoint($endpoint, $data)
{
    global $cache_cache_server;

    $cached = $cache_cache_server[$endpoint];

    if ($cached)
        return $cached;


    $cache_servers = $data['endpoints'][$endpoint]['caches'];

    $order_by_latency = usort($cache_servers, function($a, $b) {
        return $a['latency'] > $b['latency'];
    });

    if (is_array($cache_servers) && count($cache_servers) > 0) {
        $cache_cache_server[$endpoint] = $cache_servers[0];
        return $cache_servers[0];
    }
    else {
        die('CACHESERVERS ERRORE DI MERDA');
    }
    die ('CACHESERVERS  ERRORE DI MERDA');
}

function get_cache_server_list_for_endpoint($endpoint, $data)
{
    $cache_servers = $data['endpoints'][$endpoint]['caches'];

    $order_by_latency = usort($cache_servers, function($a, $b) {
        return $a['latency'] > $b['latency'];
    });

    if (is_array($cache_servers) && count($cache_servers) > 0) {
        return $cache_servers;
    }
    else {
        die('CACHESERVERS ERRORE DI MERDA');
    }
    die ('CACHESERVERS  ERRORE DI MERDA');
}

function get_endpoints_with_major_datacenter_latency($data)
{
    $endpoints = $data['endpoints'];

    $order_by_latency = usort($endpoints, function($a, $b) {
        return $a['datacenter_latency'] < $b['datacenter_latency'];
    });

    if (is_array($endpoints) && count($endpoints) > 0) {
        return $endpoints;
    }
    else {
        die('ENDPOINTS ERRORE DI MERDA');
    }
    die ('ENDPOINTS ALTRO ERRORE DI MERDA');
}

function get_videos_for_endpoint_ordered($endpoint, $videos)
{

    $result = Array();
    foreach ($videos as $video)
    {

        foreach ($video as $video_at_endpoint)
        {

            $endpoint_data = $video_at_endpoint['endpoint_data'];

            foreach ($endpoint_data as $e_data)
            {
                if ($e_data['endpoint_origin'] == $endpoint)
                {
                    $result[$video_at_endpoint['video_number']] = $e_data;
                    $result[$video_at_endpoint['video_number']]['video_number'] =  $video_at_endpoint['video_number'];
                }
            }

        }
    }

    // order by number of requests asc
    usort($result, function($a, $b) {
        return $a['number_of_requests'] < $b['number_of_requests'];
    });

    // then order by weight asc?
    usort($result, function($a, $b) {
        return $a['weight'] > $b['weight'];
    });

    return $result;
}

function get_video_weight($video)
{
    global $h_data;
    return $h_data['videos'][$video];
}


function add_to_cache_server($cache_server_number, $video_number)
{
    global $final_results;
    global $max_cache_weight;

    $video_weight = get_video_weight($video_number);
    $actual_weight = $final_results[$cache_server_number]['actual_weight'];

    if (($actual_weight+$video_weight) > $max_cache_weight)
    {
        return false;
    } else {
        if (!in_array($video_number, $final_results[$cache_server_number]['videos']))
        {
            $final_results[$cache_server_number]['videos'][] = $video_number;
            $final_results[$cache_server_number]['actual_weight'] += $video_weight;

            echo "Adding video ".$video_number." to cache server ".$cache_server_number."\n";
        }
        return true;

    }

}

function print_result()
{
    global $final_results;
    global $h_file;

    $output = "";

    $output .= count($final_results);
    $output .= "\n";
    foreach ($final_results as $key => $result)
    {
        $line = "$key ";
        foreach ($result['videos'] as $item) {
            $line .= $item." ";
        }

        $line = substr($line, 0, -1);
        $output .= $line."\n";
    }

    file_put_contents($h_file.".out", $output);
}


//print_r($o_videos);

