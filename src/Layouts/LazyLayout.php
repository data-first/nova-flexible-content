<?php

namespace Whitecube\NovaFlexibleContent\Layouts;

use JsonSerializable;
use ReflectionClass;
use Whitecube\NovaFlexibleContent\Http\ScopedRequest;

/**
 * Proxy class for deferred layout instantiation.
 *
 * This class holds a layout class name and only instantiates
 * the real layout when its fields are actually needed.
 */
class LazyLayout implements LayoutInterface, JsonSerializable
{
    /**
     * The layout class name
     *
     * @var string
     */
    protected string $layoutClass;

    /**
     * The instantiated layout (lazy loaded)
     *
     * @var Layout|null
     */
    protected ?Layout $instance = null;

    /**
     * Cached schema data (name, title, limit)
     *
     * @var array|null
     */
    protected ?array $schema = null;

    /**
     * Static cache for layout schemas across all instances
     *
     * @var array
     */
    protected static array $schemaCache = [];

    /**
     * Create a new LazyLayout instance
     *
     * @param string $layoutClass
     */
    public function __construct(string $layoutClass)
    {
        $this->layoutClass = $layoutClass;
    }

    /**
     * Get or create the real layout instance
     *
     * @return Layout
     */
    public function getInstance(): Layout
    {
        if ($this->instance === null) {
            $this->instance = new $this->layoutClass();
        }

        return $this->instance;
    }

    /**
     * Get the layout class name
     *
     * @return string
     */
    public function getLayoutClass(): string
    {
        return $this->layoutClass;
    }

    /**
     * Get lightweight schema without full instantiation
     *
     * @return array
     */
    protected function getSchema(): array
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        if (isset(self::$schemaCache[$this->layoutClass])) {
            $this->schema = self::$schemaCache[$this->layoutClass];
            return $this->schema;
        }

        // Use reflection to get default property values without instantiation
        $reflection = new ReflectionClass($this->layoutClass);

        $name = $this->getDefaultPropertyValue($reflection, 'name');
        $title = $this->getDefaultPropertyValue($reflection, 'title');
        $limit = $this->getDefaultPropertyValue($reflection, 'limit');

        // If name() or title() methods exist and return different values,
        // we need to instantiate to get the correct values
        // For now, we'll use the property defaults which works for most layouts

        $this->schema = [
            'name' => $name,
            'title' => $title,
            'limit' => $limit,
        ];

        self::$schemaCache[$this->layoutClass] = $this->schema;

        return $this->schema;
    }

    /**
     * Get the default value of a property using reflection
     *
     * @param ReflectionClass $reflection
     * @param string $property
     * @return mixed
     */
    protected function getDefaultPropertyValue(ReflectionClass $reflection, string $property): mixed
    {
        if (!$reflection->hasProperty($property)) {
            return null;
        }

        $prop = $reflection->getProperty($property);

        // Check if the property has a default value
        if ($prop->hasDefaultValue()) {
            return $prop->getDefaultValue();
        }

        return null;
    }

    /**
     * Retrieve the layout's name (identifier)
     *
     * @return string
     */
    public function name()
    {
        return $this->getSchema()['name'];
    }

    /**
     * Retrieve the layout's title
     *
     * @return string
     */
    public function title()
    {
        return $this->getSchema()['title'];
    }

    /**
     * Retrieve the layout's fields - requires instantiation
     *
     * @return array
     */
    public function fields()
    {
        return $this->getInstance()->fields();
    }

    /**
     * Retrieve the layout's unique key
     *
     * @return string
     */
    public function key()
    {
        return $this->getInstance()->key();
    }

    /**
     * Resolve and return the result
     *
     * @return array
     */
    public function getResolved()
    {
        return $this->getInstance()->getResolved();
    }

    /**
     * Resolve fields using given attributes
     *
     * @param bool $empty
     * @return void
     */
    public function resolve($empty = false)
    {
        $this->getInstance()->resolve($empty);
    }

    /**
     * Fill attributes using underlaying fields and incoming request
     *
     * @param ScopedRequest $request
     * @return array
     */
    public function fill(ScopedRequest $request)
    {
        return $this->getInstance()->fill($request);
    }

    /**
     * Get an empty cloned instance
     *
     * @param string $key
     * @return Layout
     */
    public function duplicate($key)
    {
        return $this->getInstance()->duplicate($key);
    }

    /**
     * Get a cloned instance with set values
     *
     * @param string $key
     * @param array $attributes
     * @return Layout
     */
    public function duplicateAndHydrate($key, array $attributes = [])
    {
        return $this->getInstance()->duplicateAndHydrate($key, $attributes);
    }

    /**
     * Transform layout for serialization - lightweight version
     *
     * Returns minimal data for the dropdown menu. Fields are loaded
     * on-demand via AJAX when the layout is selected.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $schema = $this->getSchema();

        return [
            'name' => $schema['name'],
            'title' => $schema['title'],
            'limit' => $schema['limit'],
            'fields' => null,  // Deferred - loaded via AJAX
            'lazy' => true,
            'layoutClass' => $this->layoutClass,
        ];
    }

    /**
     * Clear the static schema cache (useful for testing)
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$schemaCache = [];
    }
}
