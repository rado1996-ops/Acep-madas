<?php

/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */
namespace MailPoetVendor\Doctrine\DBAL\Types;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Doctrine\DBAL\Platforms\AbstractPlatform;
use MailPoetVendor\Doctrine\DBAL\DBALException;
/**
 * The base class for so-called Doctrine mapping types.
 *
 * A Type object is obtained by calling the static {@link getType()} method.
 *
 * @author Roman Borschel <roman@code-factory.org>
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @since  2.0
 */
abstract class Type
{
    const TARRAY = 'array';
    const SIMPLE_ARRAY = 'simple_array';
    const JSON_ARRAY = 'json_array';
    const BIGINT = 'bigint';
    const BOOLEAN = 'boolean';
    const DATETIME = 'datetime';
    const DATETIMETZ = 'datetimetz';
    const DATE = 'date';
    const TIME = 'time';
    const DECIMAL = 'decimal';
    const INTEGER = 'integer';
    const OBJECT = 'object';
    const SMALLINT = 'smallint';
    const STRING = 'string';
    const TEXT = 'text';
    const BINARY = 'binary';
    const BLOB = 'blob';
    const FLOAT = 'float';
    const GUID = 'guid';
    /**
     * Map of already instantiated type objects. One instance per type (flyweight).
     *
     * @var array
     */
    private static $_typeObjects = array();
    /**
     * The map of supported doctrine mapping types.
     *
     * @var array
     */
    private static $_typesMap = array(self::TARRAY => 'MailPoetVendor\\Doctrine\\DBAL\\Types\\ArrayType', self::SIMPLE_ARRAY => 'MailPoetVendor\\Doctrine\\DBAL\\Types\\SimpleArrayType', self::JSON_ARRAY => 'MailPoetVendor\\Doctrine\\DBAL\\Types\\JsonArrayType', self::OBJECT => 'MailPoetVendor\\Doctrine\\DBAL\\Types\\ObjectType', self::BOOLEAN => 'MailPoetVendor\\Doctrine\\DBAL\\Types\\BooleanType', self::INTEGER => 'MailPoetVendor\\Doctrine\\DBAL\\Types\\IntegerType', self::SMALLINT => 'MailPoetVendor\\Doctrine\\DBAL\\Types\\SmallIntType', self::BIGINT => 'MailPoetVendor\\Doctrine\\DBAL\\Types\\BigIntType', self::STRING => 'MailPoetVendor\\Doctrine\\DBAL\\Types\\StringType', self::TEXT => 'MailPoetVendor\\Doctrine\\DBAL\\Types\\TextType', self::DATETIME => 'MailPoetVendor\\Doctrine\\DBAL\\Types\\DateTimeType', self::DATETIMETZ => 'MailPoetVendor\\Doctrine\\DBAL\\Types\\DateTimeTzType', self::DATE => 'MailPoetVendor\\Doctrine\\DBAL\\Types\\DateType', self::TIME => 'MailPoetVendor\\Doctrine\\DBAL\\Types\\TimeType', self::DECIMAL => 'MailPoetVendor\\Doctrine\\DBAL\\Types\\DecimalType', self::FLOAT => 'MailPoetVendor\\Doctrine\\DBAL\\Types\\FloatType', self::BINARY => 'MailPoetVendor\\Doctrine\\DBAL\\Types\\BinaryType', self::BLOB => 'MailPoetVendor\\Doctrine\\DBAL\\Types\\BlobType', self::GUID => 'MailPoetVendor\\Doctrine\\DBAL\\Types\\GuidType');
    /**
     * Prevents instantiation and forces use of the factory method.
     */
    private final function __construct()
    {
    }
    /**
     * Converts a value from its PHP representation to its database representation
     * of this type.
     *
     * @param mixed                                     $value    The value to convert.
     * @param \MailPoetVendor\Doctrine\DBAL\Platforms\AbstractPlatform $platform The currently used database platform.
     *
     * @return mixed The database representation of the value.
     */
    public function convertToDatabaseValue($value, \MailPoetVendor\Doctrine\DBAL\Platforms\AbstractPlatform $platform)
    {
        return $value;
    }
    /**
     * Converts a value from its database representation to its PHP representation
     * of this type.
     *
     * @param mixed                                     $value    The value to convert.
     * @param \MailPoetVendor\Doctrine\DBAL\Platforms\AbstractPlatform $platform The currently used database platform.
     *
     * @return mixed The PHP representation of the value.
     */
    public function convertToPHPValue($value, \MailPoetVendor\Doctrine\DBAL\Platforms\AbstractPlatform $platform)
    {
        return $value;
    }
    /**
     * Gets the default length of this type.
     *
     * @param \MailPoetVendor\Doctrine\DBAL\Platforms\AbstractPlatform $platform
     *
     * @return integer|null
     *
     * @todo Needed?
     */
    public function getDefaultLength(\MailPoetVendor\Doctrine\DBAL\Platforms\AbstractPlatform $platform)
    {
        return null;
    }
    /**
     * Gets the SQL declaration snippet for a field of this type.
     *
     * @param array                                     $fieldDeclaration The field declaration.
     * @param \MailPoetVendor\Doctrine\DBAL\Platforms\AbstractPlatform $platform         The currently used database platform.
     *
     * @return string
     */
    public abstract function getSQLDeclaration(array $fieldDeclaration, \MailPoetVendor\Doctrine\DBAL\Platforms\AbstractPlatform $platform);
    /**
     * Gets the name of this type.
     *
     * @return string
     *
     * @todo Needed?
     */
    public abstract function getName();
    /**
     * Factory method to create type instances.
     * Type instances are implemented as flyweights.
     *
     * @param string $name The name of the type (as returned by getName()).
     *
     * @return \MailPoetVendor\Doctrine\DBAL\Types\Type
     *
     * @throws \MailPoetVendor\Doctrine\DBAL\DBALException
     */
    public static function getType($name)
    {
        if (!isset(self::$_typeObjects[$name])) {
            if (!isset(self::$_typesMap[$name])) {
                throw \MailPoetVendor\Doctrine\DBAL\DBALException::unknownColumnType($name);
            }
            self::$_typeObjects[$name] = new self::$_typesMap[$name]();
        }
        return self::$_typeObjects[$name];
    }
    /**
     * Adds a custom type to the type map.
     *
     * @param string $name      The name of the type. This should correspond to what getName() returns.
     * @param string $className The class name of the custom type.
     *
     * @return void
     *
     * @throws \MailPoetVendor\Doctrine\DBAL\DBALException
     */
    public static function addType($name, $className)
    {
        if (isset(self::$_typesMap[$name])) {
            throw \MailPoetVendor\Doctrine\DBAL\DBALException::typeExists($name);
        }
        self::$_typesMap[$name] = $className;
    }
    /**
     * Checks if exists support for a type.
     *
     * @param string $name The name of the type.
     *
     * @return boolean TRUE if type is supported; FALSE otherwise.
     */
    public static function hasType($name)
    {
        return isset(self::$_typesMap[$name]);
    }
    /**
     * Overrides an already defined type to use a different implementation.
     *
     * @param string $name
     * @param string $className
     *
     * @return void
     *
     * @throws \MailPoetVendor\Doctrine\DBAL\DBALException
     */
    public static function overrideType($name, $className)
    {
        if (!isset(self::$_typesMap[$name])) {
            throw \MailPoetVendor\Doctrine\DBAL\DBALException::typeNotFound($name);
        }
        if (isset(self::$_typeObjects[$name])) {
            unset(self::$_typeObjects[$name]);
        }
        self::$_typesMap[$name] = $className;
    }
    /**
     * Gets the (preferred) binding type for values of this type that
     * can be used when binding parameters to prepared statements.
     *
     * This method should return one of the PDO::PARAM_* constants, that is, one of:
     *
     * PDO::PARAM_BOOL
     * PDO::PARAM_NULL
     * PDO::PARAM_INT
     * PDO::PARAM_STR
     * PDO::PARAM_LOB
     *
     * @return integer
     */
    public function getBindingType()
    {
        return \PDO::PARAM_STR;
    }
    /**
     * Gets the types array map which holds all registered types and the corresponding
     * type class
     *
     * @return array
     */
    public static function getTypesMap()
    {
        return self::$_typesMap;
    }
    /**
     * @return string
     */
    public function __toString()
    {
        $e = \explode('\\', \get_class($this));
        return \str_replace('Type', '', \end($e));
    }
    /**
     * Does working with this column require SQL conversion functions?
     *
     * This is a metadata function that is required for example in the ORM.
     * Usage of {@link convertToDatabaseValueSQL} and
     * {@link convertToPHPValueSQL} works for any type and mostly
     * does nothing. This method can additionally be used for optimization purposes.
     *
     * @return boolean
     */
    public function canRequireSQLConversion()
    {
        return \false;
    }
    /**
     * Modifies the SQL expression (identifier, parameter) to convert to a database value.
     *
     * @param string                                    $sqlExpr
     * @param \MailPoetVendor\Doctrine\DBAL\Platforms\AbstractPlatform $platform
     *
     * @return string
     */
    public function convertToDatabaseValueSQL($sqlExpr, \MailPoetVendor\Doctrine\DBAL\Platforms\AbstractPlatform $platform)
    {
        return $sqlExpr;
    }
    /**
     * Modifies the SQL expression (identifier, parameter) to convert to a PHP value.
     *
     * @param string                                    $sqlExpr
     * @param \MailPoetVendor\Doctrine\DBAL\Platforms\AbstractPlatform $platform
     *
     * @return string
     */
    public function convertToPHPValueSQL($sqlExpr, $platform)
    {
        return $sqlExpr;
    }
    /**
     * Gets an array of database types that map to this Doctrine type.
     *
     * @param \MailPoetVendor\Doctrine\DBAL\Platforms\AbstractPlatform $platform
     *
     * @return array
     */
    public function getMappedDatabaseTypes(\MailPoetVendor\Doctrine\DBAL\Platforms\AbstractPlatform $platform)
    {
        return array();
    }
    /**
     * If this Doctrine Type maps to an already mapped database type,
     * reverse schema engineering can't take them apart. You need to mark
     * one of those types as commented, which will have Doctrine use an SQL
     * comment to typehint the actual Doctrine Type.
     *
     * @param \MailPoetVendor\Doctrine\DBAL\Platforms\AbstractPlatform $platform
     *
     * @return boolean
     */
    public function requiresSQLCommentHint(\MailPoetVendor\Doctrine\DBAL\Platforms\AbstractPlatform $platform)
    {
        return \false;
    }
}
