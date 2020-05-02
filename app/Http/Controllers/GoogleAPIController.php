<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\UserModel;
use \Firebase\JWT\JWT;
use App\Controllers\RouteController;
use ShortestPath;

class GoogleAPIController extends Controller
{
    //-------------------------------------------------------------------
    //------------------------ Google API Functions ---------------------
    //-------------------------------------------------------------------
    //---------------------------- Routes -------------------------------
    //Ovo je glavna funkcija unutar naše aplikacije.
    //Kao parametre u requestu dobivam origin, destination i waypoints latitude i longitude
    //Kao response vraća se locations array koji ima objekt s atributima: place_id, latitude,
    //                                              longitude, duration, distance
    //                                          -> odnosi se na sljedeći landmark
    //Također ima route -> overview_polyline_points iz responsea
    //Također ima distance -> ukupni distance
    //Također ima duration -> ukupni duration
    public function newRoute(Request $request){
        //Token check
        if(!JWTValidation($request)){
            return response()->json(["Error"=>"Unauthorized"],401);
        }
        return ShortestPath::getShortestPath($request->origin,$request->destination,$request->waypoints);
    }



    //---------------------------- Get Nearby ---------------------------
    //Get nearby global function.
    public function getNearby(Request $request,$type){
        if(!JWTValidation($request)){
            return response()->json(["Error"=>"Unauthorized"],401);
        }else{
            $latitude=$request['lat'];
            $longitude=$request['long'];

            if(is_null($longitude) || is_null($latitude))
                return response()->json(["Error"=>"Bad Request."],400);

            $link="https://maps.googleapis.com/maps/api/place/nearbysearch/json?location=".$latitude.",".$longitude."&radius=1500&type=".$type."&key=".env("GOOGLE_API_KEY","somedefaultvalue");
            $response=json_decode(file_get_contents($link));
            $response=$response->results;

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

            return $responseArray;
        }
    }

    //Specific get nearby functions
    public function getNearbyRestaurants(Request $request){
        $restaurants = self::getNearby($request,"restaurant");
        return response()->json($restaurants,200);
    }

    public function getNearbyCafes(Request $request){
        $bar_json=self::getNearby($request,"bar");
        $cafe_json=self::getNearby($request,"cafe");
        $mergedArray=array_merge($bar_json,$cafe_json);
        $mergedArray=self::RemoveDuplicates($mergedArray);
        return response()->json($mergedArray,200);
    }

    public function getNearbyShops(Request $request){
        $shopping_mall_json=self::getNearby($request,"shopping_mall");
        $store_json=self::getNearby($request,"store");
        $supermarket_json=self::getNearby($request,"supermarket");
        $mergedArray=  array_merge($shopping_mall_json,$store_json,$supermarket_json);
        $mergedArray= self::RemoveDuplicates($mergedArray);
        return response()->json($mergedArray,200);
    }

    public function getNearbyAttractions(Request $request){
        $tourist_attraction_json=self::getNearby($request,"tourist_attraction");
        $amusement_park_json=self::getNearby($request,"amusement_park");
        $art_gallery_json=self::getNearby($request,"art_gallery");
        $synagogue_json=self::getNearby($request,"synagogue");
        $city_hall_json=self::getNearby($request,"city_hall");
        $courthouse_json=self::getNearby($request,"courthouse");
        $embassy_json=self::getNearby($request,"embassy");
        $museum_json=self::getNearby($request,"museum");
        $library_json=self::getNearby($request,"library");
        $park_json=self::getNearby($request,"park");
        $stadium_json=self::getNearby($request,"stadium");

        $mergedArray=array_merge($tourist_attraction_json,$amusement_park_json,$art_gallery_json,
                $synagogue_json,$city_hall_json,$courthouse_json,$embassy_json,
                $library_json,$park_json,$stadium_json);
        $mergedArray=self::RemoveDuplicates($mergedArray);
        return response()->json($mergedArray,200);
    }

    //Used GeoNames API
    public function getNearbyCities(Request $request){
        if(!JWTValidation($request)){
            return response()->json(["Error"=>"Unauthorized."],401);
        }

        //Get latitude and longitude from JSON
        $latitude=$request['lat'];
        $longitude=$request['long'];

        //Check longitude and latitude
        if($latitude == null || $longitude == null){
            return response()->json(["Error"=>"Bad Request"],400);
        }

        $nearbyCities=self::getNearbyPlacesArray($latitude,$longitude);

        return response()->json($nearbyCities,200);
    }

    private function getNearbyPlacesArray($latitude,$longitude){
        $responseStyle = 'short'; // the length of the response
        $citySize = 'cities5000'; // the minimal number of citizens a city must have
        $radius = 100; // the radius in KM
        $maxRows = 10; // the maximum number of rows to retrieve
        $username = 'johndoe'; // the username of your GeoNames account
        
        // get nearby cities based on range as array from The GeoNames API
        $url='http://api.geonames.org/findNearbyPlaceNameJSON?lat='.$latitude.'&lng='.$longitude.'&style='.$responseStyle.'&cities='.$citySize.'&radius='.$radius.'&maxRows='.$maxRows.'&username='.$username;
        $nearbyCities = json_decode(file_get_contents($url))->geonames;

        $returnMe=array();
        foreach($nearbyCities as $city){
            $cityName=$city->name;
            $photoReference=self::getPhotoReference($cityName);
            if($photoReference!="null"){
                array_push($returnMe,["city"=>$cityName,"photo_reference"=>$photoReference]);
            }
        }

        return $returnMe;
    }


    //---------------------------- Get Attractions ---------------------------
    //Get attractions global function.
    public function getAttraction(Request $request,$type){
        if(!JWTValidation($request)){
            return response()->json(["Error"=>"Unauthorized"],401);
        }else{
            $location=$request['location'];

            if($location==null || $type==null)
                return response()->json(["Error" => "Bad Request"],400);

            $url="https://maps.googleapis.com/maps/api/place/textsearch/json?input=".rawurlencode($location)."&inputtype=textquery&type=".$type."&key=".env("GOOGLE_API_KEY","somedefaultvalue");
            $response=json_decode(file_get_contents($url));
            
            //In needed response variable there is only info that is 
            //needed for front end
            $neededResponse=self::getAttractionInformationForFrontEnd($response);
            return $neededResponse;
        }
    }

    
    //Specific get attractions functions
    public function getAttractionParks(Request $request){
        $park_json=self::getAttraction($request,"park");

        self::DescendingSortByRating($park_json);
        return response()->json($park_json,200);
    }

    public function getAttractionStadiums(Request $request){
        $stadium_json=self::getAttraction($request,"stadium");

        self::DescendingSortByRating($stadium_json);
        return response()->json($stadium_json,200);
    }

    public function getAttractionPets(Request $request){
        $zoo_json=self::getAttraction($request,"zoo");
        $pet_store_json=self::getAttraction($request,"pet_store");
        $aquarium_json=self::getAttraction($request,"aquarium");

        $merged_json=array_merge($zoo_json,$pet_store_json,$aquarium_json);
        self::DescendingSortByRating($merged_json);
        $merged_json=self::RemoveDuplicates($merged_json);
        return response()->json($merged_json,200);
    }
    
    public function getAttractionSchools(Request $request){
        $school_json=self::getAttraction($request,"school");
        $university_json=self::getAttraction($request,"university");
        $library_json=self::getAttraction($request,"library");

        $merged_json=array_merge($school_json,$university_json,$library_json);
        self::DescendingSortByRating($merged_json);
        $merged_json=self::RemoveDuplicates($merged_json);
        return response()->json($merged_json,200);
    }

    public function getAttractionReligions(Request $request){
        $church_json=self::getAttraction($request,"church");
        $synagogue_json=self::getAttraction($request,"synagogue");

        $merged_json=array_merge($church_json,$synagogue_json);
        self::DescendingSortByRating($merged_json);
        $merged_json=self::RemoveDuplicates($merged_json);
        return $merged_json;
    }

    public function getAttractionLandmarks(Request $request){
        $tourist_attraction_json=self::getAttraction($request,"tourist_attraction");
        $art_gallery_json=self::getAttraction($request,"art_gallery");
        $city_hall_json=self::getAttraction($request,"city_hall");
        $courthouse_json=self::getAttraction($request,"courthouse");
        $embassy_json=self::getAttraction($request,"embassy");
        $museum_json=self::getAttraction($request,"museum");

        $merged_json=array_merge($tourist_attraction_json,$art_gallery_json,
                $city_hall_json,$courthouse_json,$embassy_json,$museum_json);
        self::DescendingSortByRating($merged_json);
        $merged_json=self::RemoveDuplicates($merged_json);
        return response()->json($merged_json,200);
    }
    //------------------------ Other functions ---------------------
    //This function is used to validate JWT token
    public function JWTValidation(Request $request){
        //Decode JWT
        $jwt=$request->header('Authorization');
        if(is_null($jwt))
            return false; // If there is no JWT in header
        $key = env("JWT_SECRET_KEY", "somedefaultvalue"); 
        $jwt=explode(' ',$jwt)[1]; // Remove bearer from header
        $decoded = JWT::decode($jwt, $key, array('HS256'));

        //Get info from from decoded JWT (currently in JSON format)
        $email=$decoded->email;
        $password=$decoded->password;

        //Check if user exists
        $exists=UserModel::where('email',$email)->where('password',$password)->exists();
        return $exists;
    }

    //This function is used for API endpoint /getphoto
    private function getPhotoReference($cityName){
        $url=("https://maps.googleapis.com/maps/api/place/findplacefromtext/json?input=".rawurlencode($cityName)."&inputtype=textquery&fields=photos&key=".env("GOOGLE_API_KEY","somedefaultvalue"));
        //return !((array)json_decode(file_get_contents($url))->candidates[0])?"empty":"notempty";
        if((array)json_decode(file_get_contents($url))->candidates[0]){
            return json_decode(file_get_contents($url))->candidates[0]->photos[0]->photo_reference;
        }else{
            return "null";
        }
    }

    //Ova formula služi za novo rangiranje attraction prema ratingu
    //Gleda samo one lokacije koje imaju $place->photos i više od 50 glasova.
    private function bayesovaFormula($response){
        //return (object)$response->results;

        //Izracun ratingova
        //S = wR + (1-w)C
        //w = v/v+m

        //Prvo računamo C i m
        $counter=0; // Broji koliko je zapisa
        $suma_ratinga=0.0; // Broji ukupnu sumu ratinga
        $ukupni_broj_glasova=0; // Broji ukupni broj glasova

        $places=json_decode(json_encode($response->results));
        foreach($places as $place){
            if(isset($place->photos)){ // && && $place->user_ratings_total>=50
                $counter++;
                $suma_ratinga+=$place->rating;
                $ukupni_broj_glasova+=$place->user_ratings_total;
            }
        }

        //Ipsravak buga?
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

    //This function is used to get only information that is needed
    //for front end from google API
    private function getAttractionInformationForFrontEnd($response){
        $noviRating=self::bayesovaFormula($response);

        //Vraćanje samo potrebnih informacija
        $counter=0;
        $neededInformation=array();
        $places=((object)$response)->results;
        foreach($places as $place){
            //Obavezno koristiti isset umjesto !=null
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
                   // "number_of_votes"=>$place->user_ratings_total,
                    "rating"=>$noviRating[$counter] // Tu moram staviti nove ratingove, znaci moram ih prije izracunat
                ];
                $counter++;
                array_push($neededInformation,$object);
            }
        }
        
        return $neededInformation;
    }

    private function DescendingSortByRating(&$array){
        usort($array,function($a,$b){
            return strcmp($a["rating"],$b["rating"])/(-1);
        });
    }

    //array_unique() doesn't work because photo_references between same places are SOMETIMES different so it's not a good solution
    //because of that, we will use our own user built function where we have 2 arrays: placeIdsArray (which is always unique for place)
    //and arrayWithoutDuplicates which will hold unique places
    //we go through $passedArray which contains duplicates and check whether a place exists in placeIdsArray. if it does
    //then we do not add placeId to placeIds array, nor whole place object to arrayWithoutDuplicates
    private function RemoveDuplicates($passedArray){
        $placeIdsArray=array(); 
        $arrayWithoutDuplicates=array();
        $array=json_decode(json_encode($passedArray));

        for($i=0;$i<count($array);$i++){
            if(!in_array($array[$i]->place_id,$placeIdsArray)){
                array_push($placeIdsArray,$array[$i]->place_id);
                array_push($arrayWithoutDuplicates,$array[$i]);
            }
        }
        return $arrayWithoutDuplicates;
    }




}
