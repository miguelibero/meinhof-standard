<?php

class Project
{
    protected $title;
    protected $url;
    protected $description;

    public function __construct($title, $url, $description)
    {
        $this->title = $title;
        $this->url = $url;
        $this->description = $description;
    }
    
    public function getTitle()
    {
        return $this->title;
    }
    
    public function getUrl()
    {
        return $this->url;
    }
    
    public function getDescription()
    {
        return $this->description;
    }

    public function getSlug()
    {
        return preg_replace('/[^a-z0-9]/','-', strtolower($this->title));
    }
    
    public function getViewTemplatingKey()
    {
        return 'project';
    }
}