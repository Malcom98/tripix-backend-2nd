<?php
//Function RemoveDuplicates($passedArray) is used to remove duplicates from array of objects.
//array_unique() doesn't work because photo_references between same places are SOMETIMES different so it's not a good solution
//because of that, we will use our own user built function where we have 2 arrays: placeIdsArray (which is always unique for place)
//and arrayWithoutDuplicates which will hold unique places
//we go through $passedArray which contains duplicates and check whether a place exists in placeIdsArray. if it does
//then we do not add placeId to placeIds array, nor whole place object to arrayWithoutDuplicates
//   @passedArray - Array of values where duplicates must be removed.
function RemoveDuplicates($passedArray){
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
?>