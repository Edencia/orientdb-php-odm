<?php

namespace Doctrine\ODM\OrientDB;

class OrientDBException extends \Exception
{
    /**
     * @return $this
     */
    public static function missingMappingDriverImpl()
    {
        return new self("It's a requirement to specify a Metadata Driver and pass it ".
            "to Doctrine\\ODM\\OrientDB\\Configuration::setMetadataDriverImpl().");
    }

    /**
     * @return $this
     */
    public static function detachedDocumentCannotBeRemoved()
    {
        return new self('detached document cannot be removed');
    }

    /**
     * @param $state
     *
     * @return OrientDBException
     */
    public static function invalidDocumentState($state)
    {
        return new self(sprintf('invalid document state "%s"', $state));
    }

    public static function unknownDocumentNamespace($documentNamespaceAlias)
    {
        return new self("unknown Document namespace alias '$documentNamespaceAlias'.");
    }
}