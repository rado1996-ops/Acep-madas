<?php

/**
 * LICENSE: The MIT License (the "License")
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * https://github.com/azure/azure-storage-php/LICENSE
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * PHP version 5
 *
 * @category  Microsoft
 * @package   MicrosoftAzure\Storage\Blob\Models
 * @author    Azure Storage PHP SDK <dmsh@microsoft.com>
 * @copyright 2016 Microsoft Corporation
 * @license   https://github.com/azure/azure-storage-php/LICENSE
 * @link      https://github.com/azure/azure-storage-php
 */
 
namespace MicrosoftAzure\Storage\Blob\Models;

use MicrosoftAzure\Storage\Blob\Models\BlobProperties;
use MicrosoftAzure\Storage\Common\Internal\Utilities;

/**
 * Holds result of GetBlob API.
 *
 * @category  Microsoft
 * @package   MicrosoftAzure\Storage\Blob\Models
 * @author    Azure Storage PHP SDK <dmsh@microsoft.com>
 * @copyright 2016 Microsoft Corporation
 * @license   https://github.com/azure/azure-storage-php/LICENSE
 * @version   Release: 0.11.0
 * @link      https://github.com/azure/azure-storage-php
 */
class GetBlobResult
{
    /**
     * @var BlobProperties
     */
    private $_properties;
    
    /**
     * @var array
     */
    private $_metadata;
    
    /**
     * @var StreamInterface
     */
    private $_contentStream;
    
    /**
     * Creates GetBlobResult from getBlob call.
     *
     * @param array           $headers  The HTTP response headers.
     * @param StreamInterface $body     The response body.
     * @param array           $metadata The blob metadata.
     *
     * @return GetBlobResult
     */
    public static function create($headers, $body, $metadata)
    {
        $result = new GetBlobResult();
        $result->setContentStream($body->detach());
        $result->setProperties(BlobProperties::create($headers));
        $result->setMetadata(is_null($metadata) ? array() : $metadata);
        
        return $result;
    }
    
    /**
     * Gets blob metadata.
     *
     * @return array
     */
    public function getMetadata()
    {
        return $this->_metadata;
    }

    /**
     * Sets blob metadata.
     *
     * @param array $metadata value.
     *
     * @return none
     */
    public function setMetadata($metadata)
    {
        $this->_metadata = $metadata;
    }
    
    /**
     * Gets blob properties.
     *
     * @return BlobProperties
     */
    public function getProperties()
    {
        return $this->_properties;
    }

    /**
     * Sets blob properties.
     *
     * @param BlobProperties $properties value.
     *
     * @return none
     */
    public function setProperties($properties)
    {
        $this->_properties = $properties;
    }
    
    /**
     * Gets blob contentStream.
     *
     * @return StreamInterface
     */
    public function getContentStream()
    {
        return $this->_contentStream;
    }

    /**
     * Sets blob contentStream.
     *
     * @param StreamInterface $contentStream The stream handle.
     *
     * @return none
     */
    public function setContentStream($contentStream)
    {
        $this->_contentStream = $contentStream;
    }
}
