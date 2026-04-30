Feature: Flat Gallery Backend Contract and Access Control
  In order to support a real landlord and escort gallery product safely
  As the BKP backend
  I want the flat gallery data model, access rules, and signed upload contract to be explicit before the product UI is built

  Scenario: Flat listing uses the frozen paginated contract
    Given the user is authenticated as a "Landlord"
    When the client sends a hardware-signed "GET /api/flats?per_page=1" request
    Then the backend should return the paginated Flat collection resource
    And the response should include stable pagination metadata for the mobile client

  Scenario: Landlord successfully creates a flat
    Given the user is authenticated as a "Landlord"
    When the client sends a hardware-signed "POST /api/flats" request with the required flat details
    Then the backend should persist the Flat under the authenticated Landlord
    And the response should return the created Flat resource

  Scenario: Landlord successfully uploads a photo through the signed multipart contract
    Given the authenticated user owns the target Flat
    When the client sends a hardware-signed multipart "POST /api/flats/{id}/photos" request
    Then the backend should verify the request through the hardware-signature contract
    And the backend should persist the FlatPhoto record under the target Flat
    And the response should return the created FlatPhoto resource

  Scenario: Gallery media is delivered only through the protected backend route
    Given the user is authenticated as a permitted "Landlord" or "Escort"
    When the client sends a hardware-signed "GET /api/photos/{id}/content" request
    Then the backend should stream the photo content through the protected route
    And the response must not depend on a public storage URL

  Scenario: Escort can only list flats granted through the approved access rule
    Given the user is authenticated as an "Escort"
    And the Escort has completed the future Step 3 OTP onboarding flow
    When the Escort sends a hardware-signed "GET /api/flats" request
    Then the backend should return only Flats granted through the approved invitation or access rule
    And the backend should not expose flats outside that access scope

  Scenario: Escort successfully records a verified vote
    Given the user is authenticated as an "Escort"
    And the Escort has access to the target Flat through the approved access rule
    When the Escort sends a hardware-signed "POST /api/flats/{id}/vote" request
    Then the backend should record the verified vote for that Escort and Flat
    And the response should return the updated vote state

  Scenario: Escort creates or edits a landlord report through the fixed report taxonomy
    Given the user is authenticated as an "Escort"
    And the Escort has access to the target Flat through the approved access rule
    When the Escort sends a hardware-signed "POST /api/flats/{id}/report-landlord" request with reason "pimp", "harassing", or "did_not_keep_agreement"
    Then the backend should persist the report against the Flat and Landlord
    And a later report from the same Escort against the same Landlord and Flat should edit the existing report instead of creating a duplicate row
    And the response should return the created report resource

  Scenario: Landlord creates or edits an escort report through the fixed report taxonomy
    Given the user is authenticated as a "Landlord"
    And the target Escort is tied back to the Landlord through an accepted invitation relationship
    When the Landlord sends a hardware-signed "POST /api/flats/{id}/report-escort" request with reason "drugs", "hygiene", or "did_not_pay"
    Then the backend should persist the report against the Flat and Escort
    And a later report from the same Landlord against the same Escort and Flat should edit the existing report instead of creating a duplicate row
    And the response should return the created report resource

  Scenario: Backend rejects owner-only gallery mutations from escorts
    Given the user is authenticated as an "Escort"
    When the Escort attempts to send a hardware-signed "POST /api/flats" request
    Then the backend should reject the request with a 403 Forbidden error
    When the Escort attempts to send a hardware-signed "POST /api/flats/{id}/photos" request
    Then the backend should reject the request with a 403 Forbidden error
    When the Escort attempts to send a hardware-signed "DELETE /api/photos/{id}" request
    Then the backend should reject the request with a 403 Forbidden error