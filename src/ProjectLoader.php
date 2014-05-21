<?php

use Symfony\Component\Yaml\Yaml;
use Meinhof\Model\AbstractLoader;

class ProjectLoader extends AbstractLoader
{
    protected $models = array();

    public function getModelName()
    {
        return 'project';
    }

    public function __construct($path)
    {
        $data = Yaml::parse(file_get_contents($path));
        foreach($data as $row) {
            $this->models[] = new Project(
                $row['title'], $row['url'], $row['description']);
        }
    }

    public function getModels()
    {
        return $this->models;
    }
}