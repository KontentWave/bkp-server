Feature: Landlord Flat Gallery and Role-Based Regimes
  In order to showcase rental properties and verify their quality securely
  As an authenticated Landlord (Owner) or an invited Escort (Guest)
  I want to interact with a hardware-secured image gallery that avoids app-managed persistent storage for media and gallery data
  And I want voting and reporting to stay anonymous from the counterpart's perspective

  # Step 4.2 depends on a completed Step 4.1 backend contract and on the validated iOS landlord Step 3 slice.

  # --- Owner (Landlord) Regimes ---

  Scenario: Owner successfully creates a flat from the product UI
    Given the user is logged in with the "Landlord" role
    When the Landlord creates a new Flat with the required title and listing details
    Then the app should dispatch a hardware-signed "POST /api/flats" request
    And the new Flat should immediately appear in the Landlord's dashboard
    And the product form should require title, description, phone, and email before submission
    And the gallery card should expose any configured phone, email, WhatsApp, Telegram, or Viber contact shortcuts for that listing

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

  Scenario: Owner device smoke test covers upload then delete through the gallery component
    Given the user is logged in with the "Landlord" role on a physical device
    And the secure session metadata identifies the actor as a landlord without any manual role switch
    When the Landlord loads the product gallery, uploads a photo to an owned flat, and then deletes that same photo
    Then the upload should succeed through the gallery component
    And the delete should succeed through the gallery component
    And no escort-only vote or landlord-report controls should appear during that owner flow

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
    And the Landlord-facing product UI should not reveal which Escort cast that vote

  Scenario: Guest anonymously reports a landlord from the gallery flow
    Given the Escort has already completed the future Step 3 OTP onboarding flow
    And the Escort is viewing the Flat Gallery
    When the Escort submits a fixed-reason landlord report for a specific flat
    Then the app should dispatch a hardware-signed "POST /api/flats/{id}/report-landlord" request
    And the app should confirm only that the report was submitted or updated successfully
    And the Landlord-facing product UI should not reveal which Escort submitted the report

  Scenario: Owner anonymously reports an escort from the gallery flow
    Given the user is logged in with the "Landlord" role
    And the target Escort is tied back to the Landlord through an accepted invitation relationship
    When the Landlord submits a fixed-reason escort report for a specific flat
    Then the app should dispatch a hardware-signed "POST /api/flats/{id}/report-escort" request
    And the app should confirm only that the report was submitted or updated successfully
    And the Escort-facing product UI should not reveal which Landlord submitted the report

  Scenario: Product UI exposes aggregate or personal state but not counterpart identity
    Given the user is logged in with either the "Landlord" or "Escort" role
    When the gallery renders vote and moderation state
    Then the product UI may show aggregate vote counts and the current actor's own submission state
    But the product UI must not expose voter identities, reporter identities, or per-counterparty moderation history to the reported side
    And any future reporter-identity review surface must live only in a separate admin or moderator website, not in the mobile app

  Scenario: Product role is derived from the stored secure session
    Given the device already holds a secure session token issued for either a landlord or an escort
    When the Flat Gallery opens
    Then the product UI should derive the active role from stored session metadata rather than a manual role toggle
    And if the role metadata is missing, the app should keep role-specific actions hidden until role-specific onboarding refreshes the session

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