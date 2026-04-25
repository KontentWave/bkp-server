Feature: Landlord Trust Anchors (Chrome and iOS Share Extensions)
  In order to seamlessly and securely invite Escorts to the BKP network
  As a verified Landlord
  I want to highlight phone numbers on web portals and send them directly to the BKP backend

  # --- Chrome Extension Scenarios ---

  Scenario: Landlord successfully saves their Sanctum Bearer Token in the Chrome Extension
    Given the BKP Chrome Extension is installed and opened
    When the Landlord pastes their active "Sanctum Bearer Token" into the popup interface
    And clicks the "Save" button
    Then the extension should securely store the token in "chrome.storage.local"
    And display a success message confirming the token is saved

  Scenario: Chrome Extension sanitizes highlighted text and triggers an invitation
    Given the Landlord has a valid Sanctum Token saved in the Chrome Extension
    And the Landlord is browsing a profile on "amaterky.sk"
    When the Landlord highlights the raw text "  +421 900 111 222  "
    And the Landlord right-clicks and selects "Pozvat do BKP" from the context menu
    Then the extension should intercept the highlighted text
    And sanitize the text to the exact format "+421900111222"
    And dispatch an HTTP POST request to "/api/browser/invitations" with the sanitized number
    And the request must include the saved Sanctum Token in the "Authorization: Bearer" header

  Scenario: Chrome Extension context menu is contextually restricted
    Given the BKP Chrome Extension is installed
    When the Landlord highlights text on a website other than "amaterky.sk" (e.g., "google.com")
    Then the "Pozvat do BKP" context menu option should not be visible

  # --- iOS Share Extension Scenarios ---

  Scenario: iOS Share Extension receives and sanitizes a phone number from Safari
    Given the standalone BKP React Native app is installed on the Landlord's iPhone SE
    And the Landlord is browsing a profile on "amaterky.sk" via the iOS Safari browser
    When the Landlord highlights the raw text "0900-111-222"
    And taps the native iOS "Share" button
    And selects the "BKP" app from the iOS Share Sheet
    Then the iOS Share Extension should wake up the React Native app
    And pass the highlighted text payload to the app
    And the app should sanitize the text to the standard format (e.g., "+421900111222")
    And prepare the HTTP POST payload for "/api/invitations" using the sanitized number