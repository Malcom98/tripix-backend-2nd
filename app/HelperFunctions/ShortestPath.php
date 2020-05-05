<?php
namespace App\HelperFunctions;
use Illuminate\Http\Request;

class ShortestPath{
    //This function is used when shortest path is required
    //  @origin - Starting point of a route. Has longitude and latitude.
    //  @destination - Ending point of a route. Has longitude and latitude.
    //  @waypoints - Waypoints to be visited during the route. Array of objects which contain latitude and longitude.
    public static function getShortestPath($origin,$destination,$waypoints,$userRequest=true){
        //Getting content
        $link=self::createLink($origin,$destination,$waypoints,$userRequest);
        $googleDirectionsResponse = json_decode(file_get_contents($link));
        //Data needed for response
        $locations=array();
        $polyline=$googleDirectionsResponse->routes[0]->overview_polyline->points; // Sada mi se ovo nalazi u stringu
        $total_distance=0;
        $total_duration=0;

        //Gathering data for place ids
        $coordinates=array();
        foreach($googleDirectionsResponse->routes[0]->waypoint_order as $waypoint){
            array_push($coordinates,$waypoint);
        }

        //Adding origin to locations
        $originObject=self::createOriginObject($origin,$userRequest);
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

            if($userRequest===true){
                $place_latitude=$waypoints[$coordinates[$counter-1]]["lat"];
                $place_longitude=$waypoints[$coordinates[$counter-1]]["long"];
            }else{
                $place_latitude=$waypoints[$coordinates[$counter-1]]->lat;
                $place_longitude=$waypoints[$coordinates[$counter-1]]->long;
            }
            $place_duration=explode(' ',$path->duration->text)[0];
            $place_distance=explode(' ',$path->distance->text)[0];

            $waypoint=[
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
        $destinationObject=self::createDestinationObject($destination,$destination_duration,$destination_distance,$userRequest);
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
    
    //This function is used to generate link for google directions api
    //  @origin - Starting point of a route. Has longitude and latitude.
    //  @destination - Ending point of a route. Has longitude and latitude.
    //  @waypoints - Waypoints to be visited during the route. Array of objects which contain latitude and longitude.
    //  @userRequest - true (request by user | false (request by app)
    private static function createLink($origin,$destination,$waypoints,$userRequest=true){//Making google api directions request
        if($userRequest===true){
            $link = "https://maps.googleapis.com/maps/api/directions/json?origin=".$origin["lat"].",".$origin["long"]."&waypoints=optimize:true|";
            foreach($waypoints as $waypoint){
                $link.="|".$waypoint["lat"].",".$waypoint["long"];
            }
            $link.="&destination=".$destination["lat"].",".$destination["long"]."&key=AIzaSyCFOkhSfIYP_i1w5q_Lk-3Rg81dAsCSwcE&mode=driving&language=en";
            
            return $link;
        }else{
            $link = "https://maps.googleapis.com/maps/api/directions/json?origin=".$origin->lat.",".$origin->long."&waypoints=optimize:true|";
            foreach($waypoints as $waypoint){
                $link.="|".$waypoint->lat.",".$waypoint->long;
            }
            $link.="&destination=".$destination->lat.",".$destination->long."&key=AIzaSyCFOkhSfIYP_i1w5q_Lk-3Rg81dAsCSwcE&mode=driving&language=en";
            
            return $link;
        }
    }

    //This function creates origin object
    //  @destination - Ending point of a route. Has longitude and latitude.
    //  @userRequest - true (request by user | false (request by app)
    private static function createOriginObject($origin,$userRequest){
        if($userRequest){
            $originObject=[
                "latitude"=>$origin["lat"],
                "longitude"=>$origin["long"],
                "duration"=>"0",
                "distance"=>"0"
            ];
        }else{
            $originObject=[
                "latitude"=>$origin->lat,
                "longitude"=>$origin->long,
                "duration"=>"0",
                "distance"=>"0"
            ];
        }

        return $originObject;
    }

    //This function creates destination object
    //  @origin - Starting point of a route. Has longitude and latitude.
    //  @userRequest - true (request by user | false (request by app)
    private static function createDestinationObject($destination,$duration,$distance,$userRequest){
        if($userRequest===true){
            $destinationObject=[
                //"description"=>$description,
                "latitude"=>$destination["lat"],
                "longitude"=>$destination["long"],
                "duration"=>$duration,
                "distance"=>$distance
            ];
        }else{
            $destinationObject=[
                //"description"=>$description,
                "latitude"=>$destination->lat,
                "longitude"=>$destination->long,
                "duration"=>$duration,
                "distance"=>$distance
            ];
        }

        return $destinationObject;
    }

    //This function creates an object that is requested in getShortestPath function
    //which is actually used for finding the shortest path.
    //  @route - Contains array of route objects that are in specific route.
    public static function createObjectForShortestPath($route){
        //Get number of attractions
        $numberOfAttractions=count($route);
        //Form origin object
        $origin=["lat"=>$route[0]->latitude,"long"=>$route[0]->longitude];
        //Form waypoints object
        $waypoints=array();
        for($i=1;$i<$numberOfAttractions-1;$i++){
            $waypoint=[
                "lat"=>$route[$i]->latitude,
                "long"=>$route[$i]->longitude
            ];
            array_push($waypoints,$waypoint);
        }
        //Form destination object
        $destination=["lat"=>$route[$numberOfAttractions-1]->latitude,"long"=>$route[$numberOfAttractions-1]->longitude];
        //Merge objects into route object
        $route=[
            "origin"=>$origin,
            "waypoints"=>$waypoints,
            "destination"=>$destination
        ];
        //Return response object
        return $route;
    }
}
?>