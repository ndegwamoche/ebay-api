<?php

/**
 * eBay Class
 * 
 * This class provides methods for interacting with the eBay API, including token retrieval,
 * item listing, item status checking, and item deletion.
 *
 * Author: Martin Ndegwa Moche
 * Email: ndegwamoche@gmail.com
 */

session_start();

class eBay
{
    /**
     * @var array $messages Array to store error or success messages
     */
    public $messages = [];

    /**
     * @var int $ebay_item_id eBay item ID
     */
    public $ebay_item_id = 0;

    /**
     * @var bool $error Indicates whether an error occurred during processing
     */
    public $error = false;

    /**
     * @var int $session_timeout Session timeout duration
     */
    private $session_timeout = 0;

    /**
     * @var string|null $eBay_token eBay access token
     */
    private $eBay_token = null;

    /**
     * @var string $endpoint eBay API endpoint
     */
    private $endpoint = "https://api.ebay.com/identity/v1/oauth2/token";

    /**
     * @var string $client_id eBay client ID
     */
    private $client_id = 'xxxxxxxx-xxxxxxxx-xxx-xxxxxxxxx-xxxxxxxxx';

    /**
     * @var string $secret eBay client secret
     */
    private $secret = 'xx-xxxxxxxxxxxx-xxxx-xxxx-xxxx-xxxx';

    /**
     * @var string $secret eBay refresh token
     */
    private $refresh_token = 'v^xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx==';

    /**
     * @var string|null $base64_encoded_credentials Base64 encoded client credentials
     */
    private $base64_encoded_credentials = null;

    /**
     * @var int|null $eBay_token_expires_in Expiry time for the eBay access token
     */
    private $eBay_token_expires_in = null;

    /**
     * @var string|null $eBay_token_type Type of eBay access token
     */
    private $eBay_token_type = null;

    /**
     * Constructor method
     */
    public function __construct()
    {
        // Retrieve session timeout value
        $this->session_timeout = $_SESSION["expires_in"] - time();
        // Generate base64 encoded client credentials
        $this->base64_encoded_credentials = base64_encode($this->client_id . ':' . $this->secret);
    }

    /**
     * Retrieves user access eBay token
     * 
     * @return void
     */
    private function get_user_access_eBay_token()
    {
        // Construct headers
        $headers = array(
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . $this->base64_encoded_credentials
        );

        // Initialize cURL session
        $connection = curl_init();
        // Set cURL options
        curl_setopt($connection, CURLOPT_URL, $this->endpoint);
        curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($connection, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($connection, CURLOPT_POST, 1);
        curl_setopt($connection, CURLOPT_POSTFIELDS, "grant_type=refresh_token&refresh_token=" . $this->refresh_token . "&scope=https://api.ebay.com/oauth/api_scope");
        curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($connection, CURLOPT_TIMEOUT, 5);
        // Execute cURL session
        $response = curl_exec($connection);
        // Close cURL session
        curl_close($connection);

        // Decode JSON response
        $array_data = json_decode($response, true);

        // Check if access token exists in response
        if (isset($array_data['access_token'])) {
            // Store access token and related data in session and class properties
            $_SESSION["access_token"] = $array_data['access_token'];
            $_SESSION["expires_in"] = time() + $array_data['expires_in'];
            $_SESSION["token_type"] = $array_data['token_type'];
            $this->eBay_token = $array_data['access_token'];
            $this->eBay_token_expires_in = time() + $array_data['expires_in'];
            $this->eBay_token_type = $array_data['token_type'];
        } else {
            echo "no access token";
        }
    }

    /**
     * Adds an eBay item
     * 
     * @param array $products Product data
     * @param array $description Description data
     * @param array $image Image data
     * @param array $attribute Attribute data
     * @param int|null $condition_id Condition ID
     * @param array $default_settings Default settings data
     * @param string $ecommerce_category_name E-commerce category name
     * @return void
     */
    public function add_eBay_item($products, $description, $image, $attribute, $condition_id, $default_settings, $ecommerce_category_name)
    {
        // Check if session timeout has occurred
        if ($this->session_timeout <= 0) {
            $this->get_user_access_eBay_token();
        } else {
            $this->eBay_token = $_SESSION["access_token"];
        }

        // Calculate tax

        // Set request type based on product data
        $requestType = 'AddFixedPriceItemRequest';
        $itemId = "";
        if (strlen($products['ExternalAdID']) > 6) {
            if ($this->check_listing_status($products['ExternalAdID']) != "Active") {
                $requestType = 'RelistFixedPriceItemRequest';
                $itemId = "<ItemID>{$products['ExternalAdID']}</ItemID>";
            } else {
                $requestType = 'ReviseFixedPriceItemRequest';
                $itemId = "<ItemID>{$products['ExternalAdID']}</ItemID>";
            }
        }

        // Set default condition ID
        $condition_id = $condition_id == null ? 3000 : $condition_id;

        // Extract product details
        $title = utf8_encode($products['products_model'] . " " . $products['products_name']);
        $category_id = $products['CategoryID'];
        $fixed_price = $products['price'] * $products['Tax'];
        $country = $products['Coutry'];
        $location = $products['City'];
        $currency = $products['Currency'];
        $postal_code = $products['PostalCode'];
        $qty = $products['products_quantity'] == 0 ? 1 : $products['products_quantity'];
        $merk = $products['manufacturers_name'];
        $sku = $products['products_id'];

        // Construct XML request body

        $xmlbody .= "<?xml version='1.0' encoding='utf-8'?>";
        $xmlbody .= "<{$requestType} xmlns='urn:ebay:apis:eBLBaseComponents'>";
        $xmlbody .= "<RequesterCredentials>";
        $xmlbody .= "<eBayAuthToken>$this->eBay_token</eBayAuthToken>";
        $xmlbody .= "</RequesterCredentials>";
        $xmlbody .= "<ErrorLanguage>en_US</ErrorLanguage>";
        $xmlbody .= "<WarningLevel>High</WarningLevel>";
        $xmlbody .= "<Item>";
        $xmlbody .= $itemId;
        $xmlbody .= "<ConditionID>{$condition_id}</ConditionID>";
        $xmlbody .= "<Title>" . substr($title, 0, 80) . "</Title>";
        $xmlbody .= "<Description><![CDATA[{$description['desc']} ]]></Description>";
        $xmlbody .= "<PrimaryCategory>";
        $xmlbody .= "<CategoryID>$category_id</CategoryID>";
        $xmlbody .= "</PrimaryCategory>";
        $xmlbody .= "<StartPrice>$fixed_price</StartPrice>";
        $xmlbody .= "<CategoryMappingAllowed>true</CategoryMappingAllowed>";
        $xmlbody .= "<Country>$country</Country>";
        $xmlbody .= "<Currency>$currency</Currency>";
        $xmlbody .= "<DispatchTimeMax>1</DispatchTimeMax>";
        $xmlbody .= "<ListingDuration>GTC</ListingDuration>";
        $xmlbody .= "<PostalCode>$postal_code</PostalCode>";
        $xmlbody .= "<Location>$location</Location>";
        $xmlbody .= "<Quantity>$qty</Quantity>";

        $xmlbody .= "<PictureDetails>";
        foreach ($image as $value) {
            $imageurl = "https://www.nts.nl/products/flash/content/{$value['Products_id']}/large/{$value['Image']}";
            $xmlbody .= "<PictureURL>$imageurl</PictureURL>";
        }
        $xmlbody .= "</PictureDetails>";

        $xmlbody .= "<ItemSpecifics>";

        $xmlbody .= "<NameValueList>";
        $xmlbody .= "<Name>Merk</Name>";
        $xmlbody .= "<Value><![CDATA[$merk]]></Value>";
        $xmlbody .= "</NameValueList>";

        $xmlbody .= "<NameValueList>";
        $xmlbody .= "<Name>Type</Name>";
        $xmlbody .= "<Value><![CDATA[$ecommerce_category_name]]></Value>";
        $xmlbody .= "</NameValueList>";

        foreach ($attribute as $value) {
            if ($value["attribute_name"] != "Condition") {
                $xmlbody .= "<NameValueList>";
                $xmlbody .= "<Name>{$value["attribute_name"]}</Name>";
                $xmlbody .= "<Value>{$value["attribute_value"]}</Value>";
                $xmlbody .= "</NameValueList>";
            }
        }

        $xmlbody .= "</ItemSpecifics>";

        $xmlbody .= "<SellerProfiles>";

        foreach ($default_settings as $default_value) {
            if ($default_value["attribute_name"] == "PaymentProfileName") {
                $xmlbody .= "<SellerPaymentProfile> ";
                $xmlbody .= "<{$default_value["attribute_name"]}><![CDATA[{$default_value["attribute_value"]}]]></{$default_value["attribute_name"]}>";
                $xmlbody .= "</SellerPaymentProfile>";
            } elseif ($default_value["attribute_name"] == "ReturnProfileName") {
                $xmlbody .= "<SellerReturnProfile> ";
                $xmlbody .= "<{$default_value["attribute_name"]}><![CDATA[{$default_value["attribute_value"]}]]></{$default_value["attribute_name"]}>";
                $xmlbody .= "</SellerReturnProfile>";
            } elseif ($default_value["attribute_name"] == "ShippingProfileName") {
                $xmlbody .= "<SellerShippingProfile> ";
                $xmlbody .= "<{$default_value["attribute_name"]}><![CDATA[{$default_value["attribute_value"]}]]></{$default_value["attribute_name"]}>";
                $xmlbody .= "</SellerShippingProfile>";
            }
        }

        $xmlbody .= "</SellerProfiles>";

        $xmlbody .= "<ShippingDetails>";
        $xmlbody .= "<GlobalShipping>TRUE</GlobalShipping>";
        $xmlbody .= "</ShippingDetails>";

        $xmlbody .= "<Site>Netherlands</Site>";
        $xmlbody .= "</Item>";
        $xmlbody .= "</{$requestType}>";

        // Construct headers
        $headers = array(
            'X-EBAY-API-COMPATIBILITY-LEVEL: 967',
            'X-EBAY-API-SITEID: 146',
            "X-EBAY-API-CALL-NAME: AddFixedPriceItem",
            'X-EBAY-API-IAF-TOKEN: ' . $this->eBay_token
        );

        $endpoint = "https://api.ebay.com/ws/api.dll";

        // Initialize cURL session
        $connection = curl_init();
        // Set cURL options
        curl_setopt($connection, CURLOPT_URL, $endpoint);
        curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($connection, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($connection, CURLOPT_POST, 1);
        curl_setopt($connection, CURLOPT_POSTFIELDS, $xmlbody);
        curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($connection, CURLOPT_TIMEOUT, 5);
        // Execute cURL session
        $response = curl_exec($connection);
        // Close cURL session
        curl_close($connection);

        // Parse XML response
        $xmlObject = simplexml_load_string($response);
        // Convert XML response to JSON
        $jsonString = json_encode($xmlObject);
        // Decode JSON string to PHP array
        $array_data = json_decode($jsonString, true);

        // Check for errors in response
        if (count($array_data['Errors']) != 0) {
            if (isset($array_data['Errors']["LongMessage"])) {
                $this->messages = $array_data['Errors']["LongMessage"];
            } elseif (count($array_data['Errors']) > 0) {
                $this->messages = $array_data['Errors'][0]["LongMessage"];
            } else {
                $this->messages = $array_data['Errors']["LongMessage"];
            }
            $this->error = false;
        } else {
            $this->ebay_item_id = $array_data['ItemID'];
            $this->messages = 'Product Updated -> Item ID ' . $this->ebay_item_id;
            $this->error = true;
        }
    }

    /**
     * Checks the listing status of an eBay item
     * 
     * @param int $external_ad_id External ad ID of the eBay item
     * @return string Listing status
     */
    public function check_listing_status($external_ad_id)
    {
        // Check if session timeout has occurred
        if ($this->session_timeout <= 0) {
            $this->get_user_access_eBay_token();
        } else {
            $this->eBay_token = $_SESSION["access_token"];
        }

        // Construct XML request body
        $xmlbody .= '<?xml version="1.0" encoding="utf-8"?>
           <GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
              <ItemID>' . $external_ad_id . '</ItemID>
              <RequesterCredentials>
            <eBayAuthToken>' . $this->eBay_token . '</eBayAuthToken>
          </RequesterCredentials>
           </GetItemRequest>';

        // Construct headers
        $headers = array(
            'X-EBAY-API-COMPATIBILITY-LEVEL: 967',
            'X-EBAY-API-SITEID: 146',
            "X-EBAY-API-CALL-NAME: GetItem",
            'X-EBAY-API-IAF-TOKEN: ' . $this->eBay_token
        );

        $endpoint = "https://api.ebay.com/ws/api.dll";

        // Initialize cURL session
        $connection = curl_init();
        // Set cURL options
        curl_setopt($connection, CURLOPT_URL, $endpoint);
        curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($connection, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($connection, CURLOPT_POST, 1);
        curl_setopt($connection, CURLOPT_POSTFIELDS, $xmlbody);
        curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($connection, CURLOPT_TIMEOUT, 5);
        // Execute cURL session
        $response = curl_exec($connection);
        // Close cURL session
        curl_close($connection);

        // Parse XML response
        $xmlObject = simplexml_load_string($response);
        // Convert XML response to JSON
        $jsonString = json_encode($xmlObject);
        // Decode JSON string to PHP array
        $array_data = json_decode($jsonString, true);

        // Check if response contains errors
        if (count($array_data['Errors']) != 0) {
            $this->error = false;
            $this->messages = $array_data['Errors']['ShortMessage'];
            return "Error";
        }

        // Extract listing status from response
        $listing_status = $array_data['Item']['ListingStatus'];
        $this->error = true;
        return $listing_status;
    }

    /**
     * Deletes an eBay listing.
     *
     * @param string $external_ad_id The ID of the eBay listing to be deleted.
     * 
     * @return void
     */
    public function delete_ebay_listing($external_ad_id)
    {
        // Checks if the session timeout is expired
        if ($this->session_timeout <= 0) {
            // If expired, obtains a new user access eBay token
            $this->get_user_access_eBay_token();
        } else {
            // If not expired, uses the existing eBay token from the session
            $this->eBay_token = $_SESSION["access_token"];
        }

        // Constructs the XML request body to delete the eBay listing
        $xmlbody = "<?xml version='1.0' encoding='utf-8'?>";
        $xmlbody .= "<EndFixedPriceItemRequest xmlns='urn:ebay:apis:eBLBaseComponents'>";
        $xmlbody .= "<ItemID>{$external_ad_id}</ItemID>";
        $xmlbody .= "<EndingReason>NotAvailable</EndingReason>";
        $xmlbody .= "<RequesterCredentials>";
        $xmlbody .= "<eBayAuthToken>{$this->eBay_token}</eBayAuthToken>";
        $xmlbody .= "</RequesterCredentials>";
        $xmlbody .= "</EndFixedPriceItemRequest>";

        // Sets the headers for the cURL request
        $headers = array(
            'X-EBAY-API-COMPATIBILITY-LEVEL: 967',
            'X-EBAY-API-SITEID: 146',
            "X-EBAY-API-CALL-NAME: EndFixedPriceItem",
            'X-EBAY-API-IAF-TOKEN: ' . $this->eBay_token
        );

        // Sets the eBay API endpoint
        $endpoint = "https://api.ebay.com/ws/api.dll";

        // Initializes a cURL session
        $connection = curl_init();
        curl_setopt($connection, CURLOPT_URL, $endpoint);
        curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($connection, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($connection, CURLOPT_POST, 1);
        curl_setopt($connection, CURLOPT_POSTFIELDS, $xmlbody);
        curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($connection, CURLOPT_TIMEOUT, 5);

        // Executes the cURL request and stores the response
        $response = curl_exec($connection);

        // Closes the cURL session
        curl_close($connection);

        // Parses the XML response into an associative array
        $array_data = $this->simplexml_load_string($response);

        $array_data = json_encode($array_data);

        // Uncomment the below line to display the parsed array response
        // print_r($array_data);
    }
}
