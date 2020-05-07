<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\UserModel;
use \Firebase\JWT\JWT;
use Validator;
use App\Controllers\RouteController;
use ShortestPath;

class GoogleAPIController extends Controller
{
    //---------------------------------------------------------------------------------------------------------------------------------------------------
    //--------------------------------------------------------- A P I    F U N C T I O N S --------------------------------------------------------------
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    //Function getNearbyRestaurants(Request $request) is used to get nearby restaurants based 
    //on latitude and longitude sent in request.
    //  @request - Request that was received from user.
    public function getNearbyRestaurants(Request $request){
        $types=array("restaurant");
        $all_locations=self::getNearbyPlacesByType($request,$types);
        return response()->json($all_locations,200);
    }

    //Function getNearbyRestaurants(Request $request) is used to get nearby cafes based 
    //on latitude and longitude sent in request.
    //  @request - Request that was received from user.
    public function getNearbyCafes(Request $request){
        $types=array("bar","cafe");
        $nearby_locations=self::getNearbyPlacesByType($request,$types);
        return response()->json($nearby_locations,200);
    }

    //Function getNearbyRestaurants(Request $request) is used to get nearby shops based 
    //on latitude and longitude sent in request.
    //  @request - Request that was received from user.
    public function getNearbyShops(Request $request){
        $types=array("shopping_mall","store","supermarket");
        $nearby_locations=self::getNearbyPlacesByType($request,$types);
        return response()->json($nearby_locations,200);
    }

    //Function getNearbyRestaurants(Request $request) is used to get nearby attractions based 
    //on latitude and longitude sent in request.
    //  @request - Request that was received from user.
    public function getNearbyAttractions(Request $request){
        $types=array("tourist_attraction","amusement_park","art_gallery","synagogue","city_hall",
                        "courthouse","embassy","museum","library","park","stadium");
        $nearby_locations=self::getNearbyPlacesByType($request,$types);
        return response()->json($nearby_locations,200);
    }

    //Function getNearbyCities(Request $request) is using GeoNames API
    //which gets nearby cities based on given location.
    //  @request - Request that was received from user.
    public function getNearbyCities(Request $request){
        if(!JWTValidation($request))
            return response()->json(["Error"=>"Unauthorized."],401);

        $rules=[
            'lat'=>'required',
            'long'=>'required'
        ];

        $validator=Validator::make($request->all(),$rules);
        if($validator->fails())
            return response()->json(["Error"=>"Bad request."],400);

        //Get latitude and longitude from JSON
        $latitude=$request->lat;
        $longitude=$request->long;

        $nearbyCities=self::getNearbyPlacesArray($latitude,$longitude);

        //Return response
        return response()->json($nearbyCities,200);
    }

    //Function getNearbyPlacesArray sends request to GeoCoding API
    //and retrieves nearby places in range of 100km.
    private function getNearbyPlacesArray($latitude,$longitude){
        $responseStyle = 'short'; // the length of the response
        $citySize = 'cities5000'; // the minimal number of citizens a city must have
        $radius = 100; // the radius in KM
        $maxRows = 10; // the maximum number of rows to retrieve
        $username = 'johndoe'; // the username of your GeoNames account
        
        // get nearby cities based on range as array from The GeoNames API
        $url='http://api.geonames.org/findNearbyPlaceNameJSON?lat='.$latitude.'&lng='.$longitude.'&style='.$responseStyle.'&cities='.$citySize.'&radius='.$radius.'&maxRows='.$maxRows.'&username='.$username;
        $nearbyCities = json_decode(file_get_contents($url))->geonames;

        //Create array and fill it with needed data
        $citiesArray=array();
        foreach($nearbyCities as $city){
            $cityName=$city->name;
            $photoReference=self::getPhotoReference($cityName);
            if($photoReference!="null")
                array_push($citiesArray,["city"=>$cityName,"photo_reference"=>$photoReference]);
        }

        //Return response
        return $citiesArray;
    }

    //Functions getAttractionParks(Request $request) is used to
    //get park attractions in city requested in request params.
    //  @request - Request that was received from user.
    public function getAttractionParks(Request $request){
        $types=array("park");
        $attractions=self::getAttractionsByType($request,$types);
        return response()->json($attractions,200);
    }

    //Functions getAttractionParks(Request $request) is used to
    //get stadium attractions in city requested in request params.
    //  @request - Request that was received from user.
    public function getAttractionStadiums(Request $request){
        $types=array("stadium");
        $attractions=self::getAttractionsByType($request,$types);
        return response()->json($attractions,200);
    }

    //Functions getAttractionPets(Request $request) is used to
    //get pet_store, aquarium and zoo attractions in city requested in request params.
    //  @request - Request that was received from user.
    public function getAttractionPets(Request $request){
        $types=array("pet_store","aquarium","zoo");
        $attractions=self::getAttractionsByType($request,$types);
        return response()->json($attractions,200);
    }
    
    //Functions getAttractionSchools(Request $request) is used to
    //get school, university and library attractions in city requested in request params.
    //  @request - Request that was received from user.
    public function getAttractionSchools(Request $request){
        $types=array("school","university","library");
        $attractions=self::getAttractionsByType($request,$types);
        return response()->json($attractions,200);
    }

    //Functions getAttractionReligions(Request $request) is used to
    //get church and synagogue attractions in city requested in request params.
    //  @request - Request that was received from user.
    public function getAttractionReligions(Request $request){
        $types=array("church","synagogue");
        $attractions=self::getAttractionsByType($request,$types);
        return response()->json($attractions,200);
    }

    //Functions getAttractionLandmarks(Request $request) is used to
    //get art gallery, city hall, courthouse, embassy and museum
    // attractions in city requested in request params.
    //  @request - Request that was received from user.
    public function getAttractionLandmarks(Request $request){
        $types=array("tourist_attraction","art_gallery","city_hall","courthouse","embassy","museum");
        $attractions=self::getAttractionsByType($request,$types);
        return response()->json($attractions,200);
    }
    




    //---------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------  O T H E R   F U N C T I O N S --------------------------------------------------------------
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    //Function getPhotoReference is used to get photo reference for city passed as an argument.
    //  @cityName - Name of city for which we request photo.
    private function getPhotoReference($cityName){
        $url=("https://maps.googleapis.com/maps/api/place/findplacefromtext/json?input=".rawurlencode($cityName)."&inputtype=textquery&fields=photos&key=".env("GOOGLE_API_KEY","somedefaultvalue"));
        if((array)json_decode(file_get_contents($url))->candidates[0]){
            return json_decode(file_get_contents($url))->candidates[0]->photos[0]->photo_reference;
        }else{
            return "null";
        }
    }

    //Function bayesFunction is used for new ranking by rating.
    //because places with 1 vote 5.0 rating is higher than place with 5000 votes and 4.9 rating.
    private function bayesFormula($response){
        //Izracun ratingova
        //S = wR + (1-w)C
        //w = v/v+m

        //Prvo računamo C i m
        $counter=0; // Broji koliko je zapisa
        $suma_ratinga=0.0; // Broji ukupnu sumu ratinga
        $ukupni_broj_glasova=0; // Broji ukupni broj glasova

        $places=json_decode(json_encode($response->results));
        foreach($places as $place){
            if(isset($place->photos)){ 
                $counter++;
                $suma_ratinga+=$place->rating;
                $ukupni_broj_glasova+=$place->user_ratings_total;
            }
        }

        //Rubni uvjet
        if($counter==0)
            return array();
        //Izračun C i m
        $C=$suma_ratinga/$counter;
        $m=$ukupni_broj_glasova/$counter;

        //U varijablu novi rating spremam nove ratingove
        $noviRating=array();

        //Izračun novih ratingova
        $places=((object)$response)->results;

        foreach($places as $place){
            if(isset($place->photos) && $place->user_ratings_total>=50){
                $v=$place->user_ratings_total;
                $w=$v/($v+$m);
                $R=$place->rating;
                $S=$w*$R+(1-$w)*$C;
                array_push($noviRating,$S);
            }
        }

        return $noviRating;
    }

    //Function DescendingSortByRating(&$array) sorts array in descending order
    //(higher rating at the top).
    //  @array - array of places for sorting.
    private function DescendingSortByRating(&$array){
        usort($array,function($a,$b){
            return strcmp($a["rating"],$b["rating"])/(-1);
        });
    }

    //Function getNearbyPlacesByType($request,&$types) is used to get nearby places for user
    //that is requesting specific Google API types (museums, amusement_parks, universities, etc).
    //  @request - Request that was received from user.
    //  @types - Array of google api types. (museums, amusement_parks, universities, etc)
    private function getNearbyPlacesByType($request,&$types){
        $all_locations=self::getNearby($request,$types[0]);

        for($i=1;$i<count($types);$i++){
            $new_locations=self::getNearby($request,$types[$i]);
            $all_locations=array_merge($all_locations,$new_locations);
        }
        
        $all_locations=RemoveDuplicates($all_locations);

        return $all_locations;
    }

    //Function getNearby($latitude,$longitude,$type) is used to
    //get specific nearby locations based on latitude and longitude coordinations.
    //  @latitude - Latitude of current location. 
    //  @longitude - Longitude of current location. 
    //  @Type - Google API place type. 
    public function getNearby(Request $request,$type){
        if(!JWTValidation($request))
            return response()->json(["Error"=>"Unauthorized"],401);
        
        //Get data from request
        $latitude=$request['lat'];
        $longitude=$request['long'];

        //Check if request is not valid
        if(is_null($longitude) || is_null($latitude))
            return response()->json(["Error"=>"Bad Request."],400);

        //Create link
        $link="https://maps.googleapis.com/maps/api/place/nearbysearch/json?location=".$latitude.",".$longitude."&radius=1500&type=".$type."&key=".env("GOOGLE_API_KEY","somedefaultvalue");
        $response=json_decode(file_get_contents($link));
        $response=$response->results;

        //Create response array and fill it with data
        $responseArray=array();
        foreach($response as $r){
                if(isset($r->photos[0]->photo_reference) && isset($r->rating)){
                $place_id=$r->place_id;
                $latitude=$r->geometry->location->lat;
                $longitude=$r->geometry->location->lng;
                $photo_reference=$r->photos[0]->photo_reference;
                $rating=$r->rating;
                $name=$r->name;

                $ro=[
                    "place_id"=>$place_id,
                    "latitude"=>$latitude,
                    "longitude"=>$longitude,
                    "photo_reference"=>$photo_reference,
                    "rating"=>$rating,
                    "name"=>$name
                ];
                array_push($responseArray,$ro);
            }
        }

        //Return response
        return $responseArray;
    }

    //Function getAttractionsByType($request,&$types) is used to get attraction places for user
    //that is requesting specific Google API types (museums, amusement_parks, universities, etc).
    //  @request - Request that was received from user.
    //  @types - Array of google api types. (museums, amusement_parks, universities, etc)
    private function getAttractionsByType($request,&$types){
        $all_attractions=self::getAttraction($request,$types[0]);

        for($i=1;$i<count($types);$i++){
            $new_attractions=self::getAttraction($request,$types[$i]);
            $all_attractions=array_merge($all_attractions,$new_attractions);
        }

        self::DescendingSortByRating($all_attractions);

        $all_attractions=RemoveDuplicates($all_attractions);

        return $all_attractions;
    }

    //Function getAttraction(Request $request,$type) is used to get attractions 
    //of specific type for location from request.
    //  @request - Request that was received from user.
    //  @type - Google API place type.
    public function getAttraction(Request $request,$type){
        if(!JWTValidation($request))
            return response()->json(["Error"=>"Unauthorized"],401);

        $location=$request['location'];

        if($location==null || $type==null)
            return response()->json(["Error" => "Bad Request"],400);

        $url="https://maps.googleapis.com/maps/api/place/textsearch/json?input=".rawurlencode($location)."&inputtype=textquery&type=".$type."&key=".env("GOOGLE_API_KEY","somedefaultvalue");
        $response=json_decode(file_get_contents($url));
        
        //In needed response variable there is only info that is needed for front end
        //
        $neededResponse=self::getAttractionInformationForFrontEnd($response);
        return $neededResponse;
    }

    //Function getAttractionInformationForFrontEnd($response) is function which
    //gets only necessary information for frontend.
    //  @response - Google API response from which we get only necessary data.
    private function getAttractionInformationForFrontEnd($response){
        $noviRating=self::bayesFormula($response);

        $counter=0;
        $neededInformation=array();
        $places=((object)$response)->results;
        foreach($places as $place){
            if(isset($place->photos) && $place->user_ratings_total>=50){
                $name=$place->name;
                $photo_reference=$place->photos[0]->photo_reference;
                $location=$place->geometry->location;
                $place_id=$place->place_id;
                $rating=$place->rating;
                $object=[
                    "name"=>$name,
                    "photo_reference"=>$photo_reference,
                    "location"=>$location,
                    "place_id"=>$place_id,
                    "rating"=>$noviRating[$counter] 
                ];
                $counter++;
                array_push($neededInformation,$object);
            }
        }
        
        return $neededInformation;
    }
}
