<?php

class Kint_Parser_Plugin_ClassStatics extends Kint_Parser_Plugin
{
    public function parse(&$var, Kint_Object &$o)
    {
        if (!is_object($var) || !class_exists($o->classname)) {
            return;
        }

        // Recursion or depth limit
        if (!($o instanceof Kint_Object_Instance)) {
            return;
        }

        $class = get_class($var);

        $statics = new Kint_Object_Representation('Static class properties', 'statics');

        $reflection = new ReflectionClass($class);

        // Constants
        // TODO: PHP 7.1 allows private consts. How do I get that?
        foreach ($reflection->getConstants() as $name => $val) {
            $const = Kint_Object::blank($name);
            $const->const = true;
            $const->depth = $o->depth + 1;
            $const->owner_class = $class;
            if (KINT_PHP53) {
                $const->access_path = '\\'.$class.'::'.$const->name;
            } else {
                $const->access_path = $class.'::'.$const->name;
            }
            $const->operator = Kint_Object::OPERATOR_STATIC;
            $const = $this->parser->parse($val, $const);

            $statics->contents[] = $const;
        }

        // Statics
        $pri = $pro = $pub = array();

        foreach ($reflection->getProperties(ReflectionProperty::IS_STATIC) as $static) {
            $prop = Kint_Object::blank($static->getName());
            $prop->depth = $o->depth + 1;
            $prop->static = true;
            $prop->owner_class = $static->getDeclaringClass()->name;
            $prop->operator = Kint_Object::OPERATOR_STATIC;

            $prop->access = Kint_Object::ACCESS_PUBLIC;
            if ($static->isProtected()) {
                $static->setAccessible(true);
                $prop->access = Kint_Object::ACCESS_PROTECTED;
            } elseif ($static->isPrivate()) {
                $static->setAccessible(true);
                $prop->access = Kint_Object::ACCESS_PRIVATE;
            }

            if ($this->parser->childHasPath($o, $prop)) {
                if (KINT_PHP53) {
                    $prop->access_path = '\\'.$prop->owner_class.'::$'.$prop->name;
                } else {
                    $prop->access_path = $prop->owner_class.'::$'.$prop->name;
                }
            }

            $val = $static->getValue();
            $prop = $this->parser->parse($val, $prop);

            $statics->contents[] = $prop;
        }

        if (empty($statics->contents)) {
            return;
        }

        usort($statics->contents, array('Kint_Parser_Plugin_ClassStatics', 'sort'));

        $o->addRepresentation($statics);
    }

    private static function sort(Kint_Object $a, Kint_Object $b)
    {
        $sort = ((int) $a->const) - ((int) $b->const);
        if ($sort) {
            return $sort;
        }

        $sort = Kint_Object::sortByAccess($a, $b);
        if ($sort) {
            return $sort;
        }

        return Kint_Object_Instance::sortByHierarchy($a->owner_class, $b->owner_class);
    }
}
