Feature: Core Infrastructure and Hardware Authentication
  In order to establish a secure, zero-data-at-rest communication network
  As a verified Landlord or an invited Escort
  I want to securely invite users, verify devices, and authenticate all requests using hardware cryptography

  Scenario: Landlord successfully invites an Escort
    Given a verified Landlord exists in the system with a valid session
    When the Landlord submits a request to "/api/invitations" with the phone number "+421900111222"
    Then the system should create a new "Invitation" record for "+421900111222"
    And the "Invitation" status should be "pending"
    And the system should generate a secure OTP for the invitation
    And the system should dispatch a generic SMS via Vonage to "+421900111222" without mentioning the app's nature

  Scenario: Escort successfully verifies OTP and binds their hardware key
    Given a "pending" invitation exists for "+421900111222" with the OTP "8492"
    When the Escort submits a request to "/api/verify" with the phone number "+421900111222", OTP "8492", and a generated "public_key"
    Then the system should validate the OTP
    And the system should create a new "Escort" record associated with "+421900111222"
    And the system should permanently store the provided "public_key" for that Escort
    And the "Invitation" status should be updated to "accepted"

  Scenario: Escort fails verification with an incorrect OTP
    Given a "pending" invitation exists for "+421900111222" with the OTP "8492"
    When the Escort submits a request to "/api/verify" with the phone number "+421900111222", OTP "1111", and a generated "public_key"
    Then the system should reject the request with an unauthorized error
    And the system should not create an "Escort" record
    And the "Invitation" status should remain "pending"

  Scenario: System grants access when a valid hardware signature is provided
    Given a registered Escort exists with a stored "public_key"
    When the Escort makes a request to a protected REST API endpoint
    And the request headers include "X-Hardware-Signature" containing a valid Elliptic Curve signature matching the request payload and the stored "public_key"
    Then the Hardware Security Middleware should authenticate the request
    And the system should return a successful 200 OK response

  Scenario: System denies access when the hardware signature is tampered with or invalid
    Given a registered Escort exists with a stored "public_key"
    When the Escort makes a request to a protected REST API endpoint
    And the request headers include "X-Hardware-Signature" containing an invalid or forged signature
    Then the Hardware Security Middleware should reject the request
    And the system should return a 401 Unauthorized response

  Scenario: System enforces hardware authentication for real-time WebSocket connections
    Given a registered user attempts to connect to a private Laravel Reverb channel "chat.1"
    When the connection handshake does not contain a valid hardware signature matching their stored "public_key"
    Then the system should immediately drop the WebSocket connection
    And the user should not receive any real-time broadcast events