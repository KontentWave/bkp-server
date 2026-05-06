Feature: Secure Mobile Client and Hardware Cryptography
  In order to participate in the BKP network with absolute security and privacy
  As a verified Landlord or an invited Escort
  I want my mobile app to bind to my device's hardware, sign all requests silently, and strictly prevent local data storage

  # Validated iOS landlord slice on iPhone 11 (April 29, 2026)
  Scenario: iOS landlord securely provisions a Secure Enclave signing key and bootstrap token
    Given the Landlord opens BKP on a real iOS device
    When the Landlord starts device onboarding in the BKP host app
    Then the app should trigger iOS to generate a Secure Enclave-backed Elliptic Curve (EC) key pair
    And the app should send the generated "public_key" to "/api/landlords/tokens"
    And the app should securely persist the returned Sanctum Bearer token in the encrypted device keychain
    And the private key must remain permanently locked inside the Secure Enclave

  Scenario Outline: iOS landlord submits a hardware-signed invitation from validated host-app sources
    Given the Landlord device is already onboarded on iOS
    And the BKP host app receives a sanitized phone number from <source>
    When the app finishes drafting the invitation request for "/api/invitations"
    Then the BKP host app must process the drafted request through the Secure API Interceptor
    And the host app must hardware-sign the request silently after the share handoff
    And the backend should accept the request and dispatch the Vonage SMS

    Examples:
      | source               |
      | manual input         |
      | Apple Notes share    |
      | Safari selected text |

  Scenario: Device persists a tester-facing role lock separately from the secure session
    Given the BKP app is opened on a shared tester device
    When the user selects "I am landlord" or "I am escort" on first run
    Then the app should persist that device mode separately from the bearer-token session metadata
    And the selected mode should shape the onboarding and gallery entry flow on the next launch
    And the user should be able to explicitly reset the saved device mode when recovering the device for the other role

  Scenario: OTP verification rejects a secure session that does not match the selected device role
    Given the BKP app already has a persisted tester-facing device role lock
    When the backend returns a verified actor type that does not match the selected device role
    Then the app should reject the OTP verification result
    And the app should not persist the mismatched secure session
    And the device should remain locked to the previously selected tester-facing role until it is explicitly reset

  Scenario: Verified landlord or escort can submit a hardware-signed invitation through the shared in-app flow
    Given the user is fully verified in the BKP app as either a Landlord or an Escort
    When the user submits the shared invitation form with a sanitized phone number and a selected invited role
    Then the app should send a hardware-signed request to "/api/invitations"
    And the request should preserve the selected invited role in the payload
    And the backend should accept the request for either invited role when the secure session is valid

  # Remaining Step 3 scope below is still planned behavior and has not yet been fully validated on device.

  Scenario: iOS escort securely onboards and binds a Secure Enclave hardware key
    Given the Escort has received an invitation SMS with an OTP on iOS
    When the Escort enters their phone number and the valid OTP into the BKP app
    Then the app should trigger iOS to generate a Secure Enclave-backed Elliptic Curve (EC) key pair
    And the app should send the OTP and the generated "public_key" to "/api/verify"
    And the app should securely persist the returned Sanctum Bearer token in the encrypted device keychain
    And the private key must remain permanently locked inside the Secure Enclave

  Scenario: Android escort securely onboards and binds a KeyMint hardware key
    Given the Escort has received an invitation SMS with an OTP on Android
    When the Escort enters their phone number and the valid OTP into the BKP app
    Then the app should trigger Android Keystore / KeyMint to generate a hardware-backed Elliptic Curve (EC) key pair
    And the app should send the OTP and the generated "public_key" to "/api/verify"
    And the app should securely persist the returned Sanctum Bearer token in the encrypted device keychain
    And the private key must remain permanently locked inside the Android hardware-backed keystore

  Scenario: Mobile app secures all outgoing requests with hardware signatures
    Given the user is fully onboarded and has an active session
    When the mobile app initiates a network request to any protected "/api/*" endpoint
    Then the Secure API Interceptor should generate a unique "X-Hardware-Nonce"
    And the Interceptor should generate a current "X-Hardware-Timestamp"
    And the Interceptor should construct a canonical string matching the backend's expected format (Timestamp + Nonce + Method + Path + BodyHash)
    And the app should silently sign the canonical string using the hardware private key
    And the request must be dispatched with the Sanctum token and all hardware signature headers attached

  Scenario: App enforces zero app-managed data at rest policies for network and media
    Given the BKP mobile app is running and active
    When the app fetches sensitive profile data, messages, or images from the server
    Then the HTTP client must explicitly set "Cache-Control: no-store" on the requests
    And the image loader must be configured to use memory-only caching
    And no sensitive JSON payloads or media files should be written by the app to persistent disk storage

  Scenario: Android app actively blocks screen capture and recording
    Given the BKP mobile app is open in the foreground on Android
    When the user or a background process attempts to take a screenshot or screen recording
    Then the app should enforce OS-level privacy flags such as FLAG_SECURE
    And the resulting screenshot or recording should be blocked or blacked out

  Scenario: iOS app applies best-effort screen capture mitigation
    Given the BKP mobile app is open in the foreground on iOS
    When the user or a background process attempts to take a screenshot or screen recording
    Then the app should enable iOS capture-detection handling
    And the resulting screenshot or recording should be visually shielded, redacted, or otherwise mitigated on a best-effort basis

  Scenario: App establishes a hardware-authenticated real-time WebSocket connection
    Given the user is fully onboarded and has an active session
    When the app attempts to connect to a private Laravel Reverb channel via Laravel Echo
    Then the WebSocket broadcasting authorization handshake must be routed through the Secure API Interceptor
    And the handshake request must be signed with the hardware private key
    And the app should successfully receive real-time events upon a validated handshake

  Scenario: Gallery surfaces preserve role-based privacy for votes and reports
    Given the user is fully verified and can access the flat gallery
    When the app renders landlord and escort moderation state for a flat
    Then the landlord surface may show aggregate landlord-report counts and reasons for that flat
    And the escort surface may show only the current escort's own vote and landlord-report state
    And the app must never reveal which specific counterpart voted or reported through the mobile product surface

  Scenario: Landlord can report an escort by public ad identifier even when the escort is not registered in app
    Given the Landlord is fully verified and viewing an owned flat
    When the Landlord submits an escort report using either a raw ad id or a pasted "amaterky.sk/<id>" URL
    Then the app should normalize the public ad identifier before sending the request
    And the secure request to "/api/flats/{id}/report-escort" should carry the normalized "escort_external_id"
    And the backend should accept the report even when no internal Escort record exists yet
    And the landlord-side reported-escort summary should preserve that external ad identifier for later review

  Scenario: iOS Share Extension successfully submits a hardware-signed invitation
    Given the Landlord highlights a sanitized phone number in iOS Safari and shares it to the BKP app
    When the app finishes drafting the invitation request for "/api/invitations"
    Then the BKP host app must process the drafted request through the Secure API Interceptor
    And the host app must hardware-sign the request silently after the share handoff
    And the backend should accept the request and dispatch the Vonage SMS