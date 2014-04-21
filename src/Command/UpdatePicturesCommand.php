<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of UpdatePicturesCommand
 *
 * @author gavin
 */

namespace Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Parse\Query; 


class UpdatePicturesCommand extends Command {
    const FLICKR_API = 'https://api.flickr.com/services/rest/';
    const API_KEY = 'cd4aa2a8e6445dd30179b216068f59c4';
    const USER_ID = '122510188@N08'; 
    const FORMAT = 'json';

    protected function configure() {
        $this->setName('update:pictures')
                ->setDescription('to update existing pictures albums');
    }
    protected function execute(InputInterface $input, OutputInterface $output) {
        $output->writeln('getting all the albums');
        $url = self::FLICKR_API.$this->getApiSetConfigure(); 
        $json = file_get_contents($url);
        $pictureSets = json_decode($json, TRUE);
        $pictureSets = $pictureSets['photosets'];
        $photoset = isset($pictureSets['photoset']) && is_array($pictureSets['photoset']) ? $pictureSets['photoset'] : array();
        foreach($photoset as $set){
            $title = $set['title']['_content'];
            $photos = intval($set['photos']); 
            $setId = $set['id'];
            $desc = $set['description']['_content'];
            $name = preg_replace('/\s+/', '-', strtolower($title));
            $query = new Query($this->getParseConfig(),'albums');
            $query->whereEqualTo('name', $name);
            $albums = $query->find(); 
            $results = $albums->results;
            if(empty($results)){
                $output->writeln('write into albums object');
                $object = $this->getParseAlbumObject(); 
                $object->name = $name; 
                $object->title = $title; 
                $object->desc = $desc; 
                $object->photos = $photos;
                $object->setId = $setId;
                $results = $object->save();
                
            }else{
                $results = reset($results);
            }
            
            $albumsId = $results->objectId;
            
            $this->insertPicture($output, $albumsId, $setId);
       }
    }
    protected function insertPicture(OutputInterface $output, $albumsId, $setId){
        $albumsIdObjectKey = array("__type" => "Pointer", "className" => "albums", "objectId" => $albumsId);
        $output->writeln('write into picture object for albums id:'.$albumsId.' set id:'.$setId);
        $url = self::FLICKR_API.$this->getApiPhotosBySetConfigure($setId);
        $json = file_get_contents($url);
        $pictures = json_decode($json, TRUE);
        $pictures = $pictures['photoset'];
        $pictures = isset($pictures['photo']) && is_array($pictures['photo']) ? $pictures['photo'] : array();
        $query = new Query($this->getParseConfig(),'pictures');
        $query->whereEqualTo('albums_id', $albumsIdObjectKey);
        $results = $query->find(); 
        $results = $results->results;
        $existingPictures = array(); 
        foreach($results as $result){
            $existingPictures[$result->picture_id] = $result; 
        }
        
        foreach($pictures as $picture){
            if(!isset($existingPictures[$picture['id']])){
                $object = $this->getParsePictureObject();
                $object->albums_id = $albumsIdObjectKey; 
                $object->picture_id = $picture['id'];
                $object->owner = self::USER_ID; 
                $object->secret = $picture['secret'];
                $object->server = $picture['server'];
                $object->farm = strval($picture['farm']);
                $object->title = $picture['title'];
                $object->ispublic = true; 
                $object->isfriend = false; 
                $object->isfamily = false; 
                $output->writeln('write into picture object photo id:'.$picture['id']);
                $object->save();
            }
        }
        
    }

    protected function getApiPhotosBySetConfigure($setId){
        $params = $this->getApiConfigure('flickr.photosets.getPhotos');
        $params['photoset_id'] = $setId; 
        return '?'.http_build_query($params);
    }
    protected function getApiSetConfigure(){
        $params = $this->getApiConfigure('flickr.photosets.getList');
        return '?'.http_build_query($params);
    }
    protected function getApiConfigure($method){
        return  array(
            'method' => $method, 
            'api_key'=> self::API_KEY, 
            'user_id' => self::USER_ID, 
            'format' => self::FORMAT,
            'nojsoncallback' => 1
        );
    }
    protected function getParseConfig(){
        return new \Command\ParseConfig(); 
    }

    protected function getParseAlbumObject(){
        return new \Parse\Object($this->getParseConfig(), 'albums');
    }
    
    protected function getParsePictureObject(){
        return new \Parse\Object($this->getParseConfig(), 'pictures');
    }
    
}

