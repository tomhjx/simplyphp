<?php

namespace Core\Foundation;

class Viewer
{
    private $app;

    private $attributes = [];
    private $title = '';
    private $description = '';
    private $name = '';

    /**
     * Viewer constructor.
     * @param Application $app
     */
    public function __construct(Application $app, $name)
    {
        $this->app = $app;
        $this->name = $name;
    }

    /**
     * @return Application
     */
    public function getApp()
    {
        return $this->app;
    }

    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    public function getAttribute($key, $default=null)
    {
        if (isset($this->attributes[$key])) {
            return $this->attributes[$key];
        }
        return $default;
    }

    private function include($name)
    {
        include $this->getApp()->getViewPath(str_replace('.', DIRECTORY_SEPARATOR, $name).'.php');
    }

    public function render()
    {
        ob_start();
        $this->include($this->name);
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
}