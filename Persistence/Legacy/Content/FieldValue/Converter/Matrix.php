<?php
/**
 * This file is part of the EzMatrixBundle package
 *
 * See README.md file distributed with this source code for further information.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @author For list of contributors see link in composer.json file distributed with this source code.
 */

namespace xrow\MatrixBundle\Persistence\Legacy\Content\FieldValue\Converter;

use eZ\Publish\Core\Persistence\Legacy\Content\FieldValue\Converter;
use eZ\Publish\Core\Persistence\Legacy\Content\StorageFieldDefinition;
use eZ\Publish\Core\Persistence\Legacy\Content\StorageFieldValue;
use eZ\Publish\SPI\Persistence\Content\FieldValue;
use eZ\Publish\SPI\Persistence\Content\Type\FieldDefinition;
use DOMDocument;

/**
 * Class Matrix
 * Handles conversion of Matrix fields to and from thezurückgetauscht persistence layer.
 *
 * @package EzSystems\MatrixBundle\Persistence\Legacy\Content\FieldValue\Converter
 */
class Matrix implements Converter
{
    /**
     * Factory for current class
     *
     * @note Class should instead be configured as service if it gains dependencies.
     *
     * @return Matrix
     */
    public static function create()
    {
        return new self;
    }

    /**
     * Converts data from $value to $storageFieldValue
     *
     * @param \eZ\Publish\SPI\Persistence\Content\FieldValue $value
     * @param \eZ\Publish\Core\Persistence\Legacy\Content\StorageFieldValue $storageFieldValue
     */
    public function toStorageValue( FieldValue $value, StorageFieldValue $storageFieldValue )
    {
        $storageFieldValue->dataText = $this->generateXmlString( $value->data );
    }

    /**
     * Converts data from $value to $fieldValue
     *
     * @param \eZ\Publish\Core\Persistence\Legacy\Content\StorageFieldValue $value
     * @param \eZ\Publish\SPI\Persistence\Content\FieldValue $fieldValue
     */
    public function toFieldValue( StorageFieldValue $value, FieldValue $fieldValue )
    {
        $fieldValue->data = $this->restoreValueFromXmlString( $value->dataText );
    }

    /**
     * Converts field definition data in $fieldDef into $storageFieldDef
     *
     * @param \eZ\Publish\SPI\Persistence\Content\Type\FieldDefinition $fieldDef
     * @param \eZ\Publish\Core\Persistence\Legacy\Content\StorageFieldDefinition $storageDef
     */
    public function toStorageFieldDefinition( FieldDefinition $fieldDef, StorageFieldDefinition $storageDef )
    {
        $storageDef->dataText5 = $this->generateXmlDefinitionString(
            $fieldDef->fieldTypeConstraints->fieldSettings["columns"]
        );
    }

    /**
     * Converts field definition data in $storageDef into $fieldDef
     *
     * @param \eZ\Publish\Core\Persistence\Legacy\Content\StorageFieldDefinition $storageDef
     * @param \eZ\Publish\SPI\Persistence\Content\Type\FieldDefinition $fieldDef
     */
    public function toFieldDefinition( StorageFieldDefinition $storageDef, FieldDefinition $fieldDef )
    {
        $columnsConstraints = array();
        if ( !empty( $storageDef->dataText5 ) )
        {
            $columnsConstraints['columns'] = $this->getColumnNamesFromXml( $storageDef->dataText5 );
        }
        
        $fieldDef->fieldTypeConstraints->fieldSettings = $columnsConstraints;
    }

    /**
     * Returns the name of the index column in the attribute table
     *
     * Returns the name of the index column the datatype uses, which is either
     * "sort_key_int" or "sort_key_string". This column is then used for
     * filtering and sorting for this type.
     *
     * @return string
     */
    public function getIndexColumn()
    {
        return false;
    }

    /**
     * Generates XML string from $authorValue to be stored in storage engine
     *
     * @param array $matrixValue
     *
     * @return string The generated XML string
     */
    private function generateXmlString( array $matrixValue )
    {
        /* Sample XML:
        <?xml version="1.0" encoding="utf-8"?>
        <ezmatrix>
            <name/>
            <columns number="4">
                <column id="beverage" num="0">Beverage</column>
                <column id="appetizer" num="1">Appetizer</column>
                <column id="main" num="2">Main Course</column>
                <column id="dessert" num="3">Dessert</column>
            </columns>
            <rows number="2"/>
            <c>Water</c>
            <c>Salad</c>
            <c>Steak</c>
            <c>Pie</c>
            <c>Beer</c>
            <c>Wings</c>
            <c>Pizza</c>
            <c>Ice Cream</c>
        </ezmatrix>
        */
        $doc = new DOMDocument( '1.0', 'utf-8' );

        $root = $doc->createElement( 'ezmatrix' );
        $doc->appendChild( $root );

        $root->appendChild( $doc->createElement( 'name' ) );

        $columns = $doc->createElement( 'columns' );
        $columns->setAttribute( 'number', count( $matrixValue['columns'] ) );

        $root->appendChild( $columns );

        foreach ( $matrixValue['columns'] as $column )
        {
            $columnNode = $doc->createElement( 'column' );
            $columnNode->setAttribute( 'num', $column['num'] );
            $columnNode->setAttribute( 'id', $column['id'] );
            $nameNode = $doc->createTextNode( $column['name'] );
            $columnNode->appendChild( $nameNode );

            $columns->appendChild( $columnNode );

            unset( $columnNode );
            unset( $nameNode );
        }

        $rowsNode = $doc->createElement( 'rows' );
        $rowsNode->setAttribute( 'number', count( $matrixValue['rows'] ) );

        $root->appendChild( $rowsNode );

        foreach ( $matrixValue['rows'] as $row )
        {
            foreach ( $row as $value )
            {
                $cNode = $doc->createElement( 'c' );
                $valueNode = $doc->createTextNode( $value );
                $cNode->appendChild( $valueNode );

                $root->appendChild( $cNode );

                unset( $cNode );
                unset( $valueNode );
            }
        }
        return $doc->saveXML();
    }

    /**
     * Restores an author Value object from $xmlString
     *
     * @param string $xmlString XML String stored in storage engine
     *
     * @return \eZ\Publish\Core\FieldType\Author\Value
     */
    private function restoreValueFromXmlString( $xmlString )
    {
        /* Sample XML:
        <?xml version="1.0" encoding="utf-8"?>
        <ezmatrix>
            <name/>
            <columns number="4">
                <column id="beverage" num="0">Beverage</column>
                <column id="appetizer" num="1">Appetizer</column>
                <column id="main" num="2">Main Course</column>
                <column id="dessert" num="3">Dessert</column>
            </columns>
            <rows number="2"/>
            <c>Water</c>
            <c>Salad</c>
            <c>Steak</c>
            <c>Pie</c>
            <c>Beer</c>
            <c>Wings</c>
            <c>Pizza</c>
            <c>Ice Cream</c>
        </ezmatrix>
        */
        $dom = new DOMDocument( '1.0', 'utf-8' );

        $columns = array();
        $rows = array();

        if ( $xmlString != '' && $dom->loadXML( $xmlString ) === true )
        {
            foreach ( $dom->getElementsByTagName( 'column' ) as $column )
            {
                $columns[] = array(
                    'num' => $column->getAttribute( 'num' ),
                    'id' => $column->getAttribute( 'id' ),
                    'name' => $column->nodeValue
                );
            }

            $i = 0;
            $columnLength = count( $columns );
            $row = array();
            foreach ( $dom->getElementsByTagName( 'c' ) as $rowItem )
            {
                $columnId = $columns[$i]['id'];
                $row[$columnId] = (string)$rowItem->nodeValue;

                $i++;
                if ( $i >= $columnLength )
                {
                    $rows[] = $row;
                    $row = array();
                    $i = 0;
                }
            }
            if ( count( $row ) )
            {
                $rows[] = $row;
            }
        }

        return array( 'rows' => $rows, 'columns' => $columns );
    }
    
    /**
     * Gets an array of columns ids from the text stored in the 'data_text5' column
     *
     * @param string $xmlText
     */
    private function getColumnNamesFromXml( $xmlString = '' )
    {
        $dom = new DOMDocument( '1.0', 'utf-8' );
        $columns = array();
        if ( $dom->loadXML( $xmlString ) === true )
        {
            foreach ( $dom->getElementsByTagName( 'column-name' ) as $column )
            {
                $columns[] = array(
                    'num' => $column->getAttribute( 'idx' ),
                    'id' => $column->getAttribute( 'id' ),
                    'name' => $column->nodeValue
                );
            }
        }
        return $columns;
    }
    

    /**
     * Generates the xml to be saved in the 'data_text5' column
     * of the 'ezcontenclass_attribute' row for the field
     *
     * @param array $columns
     *
     * @return string
     */
    private function generateXmlDefinitionString( array $columns )
    {
        $doc = new DOMDocument( '1.0', 'utf-8' );
        $root = $doc->createElement( 'ezmatrix' );
        foreach ( $columns as $index => $data )
        {
            $column = $doc->createElement( 'column-name', $data['name'] );
            $column->setAttribute( 'id', $data['id'] );
            $column->setAttribute( 'idx', $data['num'] );
            $root->appendChild( $column );
        }
        $doc->appendChild( $root );
        return $doc->saveXML();
    }
}
