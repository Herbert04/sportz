<?php 

include 'Connector.php';
include 'FootballData.php';

   
    $con = new Connector();
    $api = new FootballData();
    $response = $api->findteamByCompetition(2013);

    foreach ($response->teams as $teams){

        $insert = $con->query(
            "INSERT INTO basic_teams (`status`,`URL`,`id_team`,`id_pais`,`name`,`shortName`,`tla`,`crestUrl`,`address`,`phone`,`website`,`email`,`founded`,`clubColors`,`venue`,`status_date`)VALUES ('1','',?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())",
            array("iisssssssssss", intval($teams->id) . "", intval($teams->area->id) . "", $teams->name . "", $teams->shortName . "", $teams->tla . "", $teams->crestUrl  . "", $teams->address  . "", $teams->phone . "",  $teams->website. "",  $teams->email . "",  $teams->founded . "",  $teams->clubColors . "",  $teams->venue. ""),
            false
        );

        if ($insert)
           echo 'incluiu: '. $teams->name.'<br>';
        else   
            echo 'erro: '. $teams->name.'<br>';
        
    }
