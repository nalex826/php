<?php

/**
 * Class CircleApi
 * A class for interacting with Circle.so API.
 */
class CircleApi
{
    // API base URL
    public const API_URL = 'https://app.circle.so/api/v1/';

    // Your community ID
    public const COMMUNITYID = 'XXXX';

    /**
     * Makes a request to the Circle.so API.
     *
     * @param string     $method The HTTP method (GET, POST, PATCH, DELETE, PUT)
     * @param string     $path   The API endpoint path
     * @param array|null $params Optional parameters for the request
     *
     * @return mixed The API response
     */
    private function makeRequest($method, $path, $params = null)
    {
        // Construct the full API endpoint URL
        $endpoint = self::API_URL . $path . '?' . http_build_query($params);

        // Perform the API call using cURL
        return $this->curlApi($method, $endpoint);
    }

    /**
     * Executes a cURL request to the specified endpoint.
     *
     * @param string $method   The HTTP method (GET, POST, PATCH, DELETE, PUT)
     * @param string $endpoint The full URL of the API endpoint
     *
     * @return mixed The API response
     */
    private function curlApi($method, $endpoint)
    {
        // Initialize cURL
        $curl = curl_init();

        // Set cURL options based on the HTTP method
        if ('POST' == $method) {
            curl_setopt($curl, CURLOPT_POST, true);
        } elseif ('PATCH' == $method) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
        } elseif ('DELETE' == $method) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } elseif ('PUT' == $method) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
        }

        // Set common cURL options
        curl_setopt($curl, CURLOPT_URL, $endpoint);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Token ' . CIRCLE_TOKEN, // Assuming CIRCLE_TOKEN is defined elsewhere
            'Content-Type: application/json',
        ]);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        // Execute the cURL request
        $result = curl_exec($curl);

        // Check for errors
        if (! $result) {
            echo 'Error:' . curl_error($curl);
            exit('Connection Failure');
        }

        // Close the cURL session
        curl_close($curl);

        return $result;
    }

    /**
     * Retrieves account information based on email.
     *
     * @param string $email The email address of the account
     *
     * @return mixed The API response
     */
    public function getAccount($email)
    {
        return $this->makeRequest('GET', 'community_members/search', ['community_id' => self::COMMUNITYID, 'email' => $email]);
    }

    /**
     * Deletes an account based on email.
     *
     * @param string $email The email address of the account to delete
     *
     * @return mixed The API response
     */
    public function deleteAccount($email)
    {
        return $this->makeRequest('DELETE', 'community_members', ['community_id' => self::COMMUNITYID, 'email' => $email]);
    }

    /**
     * Performs a test API call.
     *
     * @return mixed The API response
     */
    public function test()
    {
        return $this->makeRequest('GET', 'communities', []);
    }
}
