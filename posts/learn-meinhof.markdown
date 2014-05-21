---
title: Working with meinhof
updated: 04/08/2012
publish: true
categories:
    - symfony
---
I've written a static website generator in php using [symfony components](http://symfony.com/components)
called [meinhof](http://github.com/miguelibero/meinhof/).
It's still in development but this site is already build using it.
This post shows some of it's features.

<!-- more -->

### Installation

To install it use [composer](http://getcomposer.org/)

    curl -s http://getcomposer.org/installer | php
    php composer.phar -sdev create-project miguelibero/meinhof-standard path/to/install

After downloading all the dependencies you will be asked some basic
questions about your site and the first version will be generated.

### Usage

#### Structure

The basic structure of a meinhof site is the following

* `config` configuration files
* `content` content files, can be used inside pages
* `posts` blog posts
* `public` site assets processed by [assetic](https://github.com/kriswallsmith/assetic)
* `views` the site templates
* `web` the generated website
* `src` custom php classes
* `translations` translations to various languages
* `vendor` all the needed libraries are managed by [composer](http://getcomposer.org/)

#### Writing blog posts

Each post is a file in the `posts` directoy. Ths standard configuration
includes a [yaml front matter](http://jekyllrb.com/docs/frontmatter/)
(like in [jekyll](https://github.com/mojombo/jekyll)) that defines the data for the post.
After this header you add the post body, by default using [markdown](http://www.darkcoding.net/software/markdown-quick-reference/).

For example:

    ---
    title: Example blog post
    updated: 04/08/2012
    categories:
        - meinhof
    ---
    This is a **blog***!

#### Changing the views

The views are managed by default using the [twig](http://twig.sensiolabs.org/) templating engine. 
Each view has access to the `site` object that contains lists of all the models defined in the site.
Additionaly there are other variables depending on which model is being rendered.

#### Updating your site

To regenerate all of you site just run the following command:

    ./bin/meinhof update

#### Configuration

The general site configuration is stored in `config/config.yml`

### Extending

#### Global variables

To add a global variable, add it to the `config/config.yml` file.

    site:
        info:
            name: miguel.ibero.me
            slogan: 'site.slogan'
            disqus_shortname: miguel-ibero-me

Then you can access it in the template through the `site` variable.

    {{ site.info.disqus_shortname }}

#### Multiple languages

Meinhof supports translation out of the box using the
[symfony translator component](https://github.com/symfony/Translation).

First add the requirement to the composer.json dependencies.

    "require": {
        ...
        "symfony/translation":          "2.1.*"
    },

Install the dependencies

    php composer.phar install

Add the configuration option to the `config/config.yml`file.

    translation:
        default_locale: es
        locales: [ es, en ]

Create the `translations` folder if not present. The files
inside this folder have the same structure as in symfony.
CHeck out [the documentation](http://symfony.com/doc/current/book/translation.html#message-catalogues) for more info.

Now you can use the `trans` function, filter and block inside your twig templates.

#### Injecting services

Use `./bin/meinhof services` to get a list of all the available services.

To inject new services, create a `config/services.*` file with the symfony
dependency injector format. In that file you can replace existing services.

##### Overwriting the Markdown parser

For my own blog, I needed to owerwrite the default meinhof markdown parser
to generate highlighted code blocks using [google prettify](http://code.google.com/p/google-code-prettify/). This is done
easily overwriting the default markdown parser service:

    <?xml version="1.0" ?>
    <container xmlns="http://symfony.com/schema/dic/services"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

        <parameters>
            <parameter key="markdown.parser.class"
                >MarkdownParser</parameter>
        </parameters>
    </container>

Now create the new class in `src/MarkdownParser` with the code:

    use dflydev\markdown\MarkdownExtraParser as BaseMarkdownParser;

    class MarkdownParser extends BaseMarkdownParser
    {
        function _doCodeBlocks_callback($matches) {
            $codeblock = $matches[1];

            $codeblock = $this->outdent($codeblock);
            $codeblock = htmlspecialchars($codeblock, ENT_NOQUOTES);

            # trim leading newlines and trailing newlines
            $codeblock = preg_replace('/\A\n+|\n+\z/', '', $codeblock);

            $codeblock = "<pre class=\"prettyprint linenums\"><code>$codeblock\n</code></pre>";
            return "\n\n".$this->hashBlock($codeblock)."\n\n";
        }
    }

This markdown parser will replace the existing one.

#### Adding new templating engines

By default, meinhof has three templating engines:

* `templating.view` renders the view templates (by default twig)
* `templating.content` renders contents (by default markdown)
* `templating.post` renders posts (by default markdown with yaml front matter)

Yo can add additional templating engines creating a service with
the corresponging tag `templating.engine`, for example:

    <service id="smarty.templating.view"
        class="%smarty.templating.class%" public="false">
        <tag name="templating.engine" type="view" />
    </service>

For more info on de templating engine class, check out the
[symfony documentation](https://github.com/symfony/Templating).

#### Adding new site models

By default meinhof comes with the models for `page`, `post` and `category`.
But it is also possible to add other data, for example `project` with data about 
your own projects.

The first thing you need to do is write the model class in the `src` folder.

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
    }

The second step is to write a `ModelLoader`. The loader will be used
by the site model to return the list of available projects. To write
the loader you have to decide from where you want to store the project
data. This loader will try to get the data from a `config/projects.yml`
file.

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

Now inject this new loader as a service to the dependency container.

    <services>
        <service id="model.loader.project" class="ProjectLoader">
            <argument>%filesystem.paths.content%/projects.yml</argument>
            <tag name="model.loader"/>
        </service>    
    </services>

The special `model.loader` tag tells meinhof to add all the projects
to the `site` variable inside of your views.

Now write the info of your projects inside the `content/projects.yml`
file:

    - title: Meinhof
      url: https://github.com/miguelibero/meinhof
      description: static site generator in PHP 5.3 using symfony components
    - title: mvcgame
      url: https://github.com/miguelibero/mvcgame
      description: model view controller c++11 2d game library

That's it! Now you can access the loaded projects from any page using
something like:

    {% for project in site.projects %}
    <div class="project">
        <a href="{{ project.url }}">{{ project.title }}</a>
        <p>{{ project.description }}</p>
    </div>
    {% endfor %}

#### Adding new pages to show the models

First of all, add the following services.

    <service id="url_helper.project" class="%url_helper.type.class%">
        <argument>%site.urls.project%</argument>
        <tag name="url_helper" class="Project" />
    </service>
    <service id="action.update_projects" class="%action.update_models.class%">
        <argument type="service" id="model.loader.project"/>
        <argument type="service" id="exporter"/>
        <argument type="service" id="output"/>
        <argument type="collection">
            <argument key="site" type="service" id="site" />
        </argument>
        <tag name="event_listener" event="update" method="take"/>
    </service>

The first one adds an url helper that will enable you to link to your new model pages. The second one adds an update projects action that will create the project pages when updating your site.

Add an url definition to your site config:

    site:
        urls:
            project: 'project/{slug}.html'

Modify your model class to add a slug and a view templating key:

    class Project
    {
        /* ... */

        public function getSlug()
        {
            return preg_replace('/[^a-z0-9]/','-', strtolower($this->title));
        }

        public function getViewTemplatingKey()
        {
            return 'project';
        }
    }

Create a new view with the name `project.html.twig` in the views folder.

    {% extends "layout.html.twig" %}

    {% block title %}{{ parent() }} - Projects - {{ project.title }}{% endblock %}

    {% block content %}
    <article>
        <h2 class="title">{{ project.title }}</h2>
        <p>{{ project.description }}</p>
        <p><a href="{{ project.url }}">Visit</a></p>
    </article>
    {% endblock %}


Linking to your model pages is now easy using the url helper

    {% for project in site.projects %}
    <div class="project">
        <a href="{{ url(project) }}">{{ project.title }}</a>
    </div>
    {% endfor %}

There are a lot more things you can do with meinhof!
For more detailed info please look at the
[meinhof](https://github.com/miguelibero/meinhof),
[meinhof-standard](https://github.com/miguelibero/meinhof-standard)
and [miguel.ibero.me](https://github.com/miguelibero/miguel.ibero.me)
repositories.
