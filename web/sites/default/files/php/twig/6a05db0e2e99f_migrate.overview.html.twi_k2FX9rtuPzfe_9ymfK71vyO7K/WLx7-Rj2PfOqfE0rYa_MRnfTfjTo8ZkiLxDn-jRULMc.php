<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* @help_topics/migrate.overview.html.twig */
class __TwigTemplate_23d608bd8824e77fe1ee166711935d1d extends Template
{
    private Source $source;
    /**
     * @var array<string, Template>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->extensions[SandboxExtension::class];
        $this->checkSecurity();
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 5
        yield "<h2>";
        yield t("What are updating, upgrading, and migrating?", []);
        yield "</h2>
<p>";
        // line 6
        yield t("<em>Updating</em> is the process of changing from one minor version of the software to a newer version, such as from version 8.3.4 to 8.3.5, or 8.3.5 to 8.4.0. Starting with version 8.x, you can also update to major versions 9, 10, and beyond if your add-on modules, themes, and install profiles are compatible. <em>Upgrading</em> is the process of changing from an older major version of the software to a newer version, such as from version 7 to 8. <em>Migrating</em> is the process of importing data into a site.", []);
        yield "</p>
<p>";
        // line 7
        yield t("To upgrade a site from Drupal 6 or 7 to Drupal 8 or later, keeping the content and configuration the same, you will install the new version of the software and add-on modules and themes in a new site, and then migrate the content and other data from your old site into the new site.", []);
        yield "</p>
<h2>";
        // line 8
        yield t("Overview of Migrating", []);
        yield "</h2>
<p>";
        // line 9
        yield t("You can use the <em>Migration</em> group of modules to perform the migration step of upgrading from Drupal 6 or 7 to Drupal 8 or later, as well as other migrations. These modules also provide APIs that can be used by programmers to write custom software for migrations. Here are the functions of the core migration modules:", []);
        yield "</p>
<dl>
<dt>";
        // line 11
        yield t("Migrate", []);
        yield "</dt>
<dd>";
        // line 12
        yield t("Provides the underlying API for migrating data.", []);
        yield "</dd>
<dt>";
        // line 13
        yield t("Migrate Drupal", []);
        yield "</dt>
<dd>";
        // line 14
        yield t("Provides data migration from older versions of the core software into a new site.", []);
        yield "</dd>
<dt>";
        // line 15
        yield t("Migrate Drupal UI", []);
        yield "</dt>
<dd>";
        // line 16
        yield t("Provides a user interface for performing data migration from older versions of the core software into a new site.", []);
        yield "</dd>
</dl>
<p>";
        // line 18
        yield t("If the source of the data you want to migrate is a different content management system, or if the data source is a site that was built using contributed modules that the core migration modules do not support, then you will also need one or more contributed or custom modules in order to migrate your data.", []);
        yield "</p>
<h2>";
        // line 19
        yield t("Additional Resources", []);
        yield "</h2>
<ul>
<li>";
        // line 21
        yield t("<a href=\"https://www.drupal.org/docs/upgrading-drupal\">Upgrading Drupal</a>", []);
        yield "</li>
</ul>";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "@help_topics/migrate.overview.html.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  100 => 21,  95 => 19,  91 => 18,  86 => 16,  82 => 15,  78 => 14,  74 => 13,  70 => 12,  66 => 11,  61 => 9,  57 => 8,  53 => 7,  49 => 6,  44 => 5,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "@help_topics/migrate.overview.html.twig", "/var/www/html/web/core/modules/migrate/help_topics/migrate.overview.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["trans" => 5];
        static $filters = [];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                ['trans'],
                [],
                [],
                $this->source
            );
        } catch (SecurityError $e) {
            $e->setSourceContext($this->source);

            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            }

            throw $e;
        }

    }
}
