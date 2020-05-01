<?php
use Illuminate\Http\Request;

    if(!function_exists('getShortestPath')){
        function getShortestPath(Request $request){
            //Putting data from request body into php variables
            $origin=$request->origin;
            $destination=$request->destination;
            $waypoints=$request->waypoints;

            //Getting content
            $link=createLink($origin,$destination,$waypoints);
            $googleDirectionsResponse = json_decode(file_get_contents($link));
            //Data needed for response
            $locations=array();
            $polyline= $googleDirectionsResponse->routes[0]->overview_polyline->points; // Sada mi se ovo nalazi u stringu
            $total_distance=0;
            $total_duration=0;

            //Gathering data for place ids
            $coordinates=array();
            foreach($googleDirectionsResponse->routes[0]->waypoint_order as $waypoint){
                array_push($coordinates,$waypoint);
            }

            //Adding origin to locations
            $originObject=createOriginObject($origin);
            array_push($locations,$originObject);

            //Adding landmarks to location
            $counter=1;
            $destination_distance;
            $destination_duration;
            foreach($googleDirectionsResponse->routes[0]->legs as $path){
                //Skip destination jer je ona stavljena u legs kao posljednji objekt u arrayu
                if($counter==count($googleDirectionsResponse->routes[0]->legs)){
                    $destination_duration=explode(' ',$path->duration->text)[0];
                    $destination_distance=explode(' ',$path->distance->text)[0];
                    $total_distance+=explode(' ',$path->distance->text)[0];
                    $total_duration+=explode(' ',$path->duration->text)[0];
                    break;
                }

                $place_latitude=$waypoints[$coordinates[$counter-1]]["lat"];
                $place_longitude=$waypoints[$coordinates[$counter-1]]["long"];
                $place_duration=explode(' ',$path->duration->text)[0];
                $place_distance=explode(' ',$path->distance->text)[0];

                $waypoint=[
                    //"place_id"=>$place_ids[$counter-1],
                    //"description"=>$place_descriptions[$counter],
                    "latitude"=>$place_latitude,
                    "longitude"=>$place_longitude,
                    "duration"=>$place_duration,
                    "distance"=>$place_distance
                ];

                array_push($locations,$waypoint);
                $counter++;

                $total_distance+=explode(' ',$path->distance->text)[0];
                $total_duration+=explode(' ',$path->duration->text)[0];
            }

            //Adding destination to locations array
            $destinationObject=createDestinationObject($destination,$destination_duration,$destination_distance);
            array_push($locations,$destinationObject);

            //Forming response object
            $response_object=[
                "locations"=>$locations,
                "route"=>$polyline,
                "distance"=>$total_distance,
                "duration"=>$total_duration
            ];

            //Returning JSON object
            return json_encode($response_object);
        }
    }

    if(!function_exists('create_link')){
        //This function is used to generate link for google directions api
        function createLink($origin,$destination,$waypoints){//Making google api directions request
        $link = "https://maps.googleapis.com/maps/api/directions/json?origin=".$origin["lat"].",".$origin["long"]."&waypoints=optimize:true|";
        foreach($waypoints as $waypoint){
            $link.="|".$waypoint["lat"].",".$waypoint["long"];
        }
        $link.="&destination=".$destination["lat"].",".$destination["long"]."&key=AIzaSyCFOkhSfIYP_i1w5q_Lk-3Rg81dAsCSwcE&mode=driving&language=en&region=undefined";
        
        return $link;
        }
    }

    //This function creates origin object
    if(!function_exists('createOriginObject')){
        function createOriginObject($origin){
            $originObject=[
                //"place_id"=>$place_id,
                //"description"=>$description,
                "latitude"=>$origin["lat"],
                "longitude"=>$origin["long"],
                "duration"=>"0",
                "distance"=>"0"
            ];

            return $originObject;
        }
    }

    //This function creates destination object
    if(!function_exists('createDestinationObject')){
        function createDestinationObject($destination,$duration,$distance){
            $destinationObject=[
                //"description"=>$description,
                "latitude"=>$destination["lat"],
                "longitude"=>$destination["long"],
                "duration"=>$duration,
                "distance"=>$distance
            ];

            return $destinationObject;
        }
    }

?>