Feature: Landlord Flat Gallery and Role-Based Regimes
  In order to showcase rental properties and verify their quality securely
  As an authenticated Landlord (Owner) or an invited Escort (Guest)
  I want to interact with a hardware-secured image gallery that avoids app-managed persistent storage for media and gallery data

  # Step 4.2 depends on a completed Step 4.1 backend contract and on the validated iOS landlord Step 3 slice.

  # --- Owner (Landlord) Regimes ---

  Scenario: Owner successfully creates a flat from the product UI
    Given the user is logged in with the "Landlord" role
    When the Landlord creates a new Flat with the required title and listing details
    Then the app should dispatch a hardware-signed "POST /api/flats" request
    And the new Flat should immediately appear in the Landlord's dashboard

  Scenario: Owner successfully captures, compresses, and uploads a flat photo
    Given the user is logged in with the "Landlord" role
    And the Landlord opens the Owner Dashboard for a specific Flat
    When the Landlord selects a high-resolution photo using the native device image picker
    Then the app should compress and resize the image without writing app-managed gallery media to persistent storage
    And the app should dispatch a "POST /api/flats/{id}/photos" request with the compressed payload
    And the request must be processed and signed by the Secure API Interceptor
    And the successfully uploaded photo should immediately appear in the Landlord's gallery view

  Scenario: Owner securely deletes an existing flat photo
    Given the Landlord is viewing their Flat Gallery
    When the Landlord taps the "Delete" overlay button on a specific photo
    Then the app should dispatch a hardware-signed "DELETE /api/photos/{id}" request
    And the backend should delete the photo record
    And the photo should be instantly and smoothly removed from the gallery UI

  # --- Guest (Escort) Regimes ---

  Scenario: Guest views the gallery without persisting images to device storage
    Given the user is logged in with the "Escort" role
    And the Escort has already completed the future Step 3 OTP onboarding flow
    And the Escort has access to flats shared through an approved invitation or access rule
    When the Escort navigates to the Flat Gallery
    Then the app should fetch the gallery JSON data using a hardware-signed "GET /api/flats" request
    And the app should render the gallery grid using a high-performance list
    And every rendered image must enforce a memory-only caching policy ("cachePolicy='memory'")
    When the Escort fully closes the application or their session expires
    Then no app-managed flat photos or gallery JSON data should remain written to persistent disk storage

  Scenario: Guest securely votes on a flat to verify its quality
    Given the Escort has already completed the future Step 3 OTP onboarding flow
    And the Escort is viewing the Flat Gallery
    When the Escort taps the "Vote/Favorite" action on a specific flat
    Then the app should dispatch a "POST /api/flats/{id}/vote" request
    And the request must be silently signed by the Escort's secure hardware key (KeyMint/Secure Enclave)
    And the backend should record the verified vote
    And the app UI should visually update to reflect the successful vote status

  # --- Role-Based Access Control (RBAC) Enforcement ---

  Scenario: System strictly prevents a Guest from modifying the gallery
    Given the user is logged in with the "Escort" role
    When the Escort is viewing the Flat Gallery
    Then the UI must not render any "Upload", "Add", or "Delete" controls
    And the UI must not render any owner-only flat-creation controls
    And if the Escort attempts to bypass the UI and send a hardware-signed "POST /api/flats" request
    Then the backend should reject the request with a 403 Forbidden error
    And if the Escort attempts to bypass the UI and send a hardware-signed "POST /api/flats/{id}/photos" request
    Then the backend should reject the request with a 403 Forbidden error