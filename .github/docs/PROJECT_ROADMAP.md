# BKP (Byty na Krátkodobý Prenájom) - Project Roadmap 🗺️

## Project Vision

To create a highly secure, private, invite-only mobile and web ecosystem that facilitates safe contact sharing, reporting, and real-time messaging between verified short-term rental landlords and independent escort workers. The system relies on absolute data privacy (no data at rest), hardware-backed cryptographic identity verification, and bypassing Big Tech app store censorship.

---

## Phase 1: Minimal Viable Product (MVP)

_The smallest version of the software that can provide core value and validate the main idea._

### 1. The Core Infrastructure (Laravel Server)

- **Action:** Set up the centralized REST API and WebSocket server.
- **Features:**
  - Database schema for Landlords, Escorts (Girls), and Invitations.
  - Setup Laravel Reverb for real-time WebSockets.
  - Implementation of the SMS Gateway integration (Vonage) with strictly generic messaging (e.g., "You have been invited...").
  - Cryptographic signature verification middleware (to validate requests signed by native hardware).

### 2.1 The Trust Anchor (Chrome Extension for Landlords)

- **Action:** Build the initial invitation mechanism linked to `amaterky.sk`.
- **Features:**
  - Background script to detect `*://*.amaterky.sk/*` URLs.
  - Context menu injection ("Pozvat do BKP") upon highlighting a phone number.
  - Direct REST API call to Laravel to initiate the Vonage SMS invite sequence.
  - _Distribution:_ Zip file for manual "Developer Mode" sideloading.

### 2.2 The Trust Anchor (iOS Share Extension for Landlords)

- **Action:** Build the initial invitation mechanism directly into the React Native mobile app to integrate natively with iOS Safari.
- **Features:**
  - Native iOS Share Extension bundled with the main BKP app.
  - Capability to highlight a phone number on web pages (e.g., `amaterky.sk`) in Safari and send it directly to the BKP app via the native iOS "Share" sheet.
  - Secure, hardware-signed REST API call from the app to the Laravel server to trigger the Vonage SMS invitation.
  - _Distribution:_ Bundled automatically with the Ad Hoc `.ipa` build via Expo EAS (no separate installation required).

### 3. The Secure Client Apps (React Native via Expo)

- **Action:** Build the standalone mobile clients with hardware cryptography.
- **Features:**
  - **Device Binding:** Hardware key generation (Secure Enclave for iOS, KeyMint for Android) upon first login/SMS OTP verification.
  - **No Data at Rest:** Explicit configuration to disable all disk caching for network requests and images.
  - **Anti-Screen Capture:** Implementation of `FLAG_SECURE` to block screenshots/recordings.
  - **Real-time Comms:** Authenticated WebSocket connection to Laravel Reverb for instant messaging.
  - _Distribution:_ Ad Hoc `.ipa` via Expo EAS for iOS (Landlords); direct `.apk` sideload for Android (Girls).

---

## Phase 2: Core Enhancements

_Features to build immediately after the MVP is stable._

- **Reporting System:** Mechanism for landlords to flag bad actors (unpaid rent, property damage) and for girls to flag abusive landlords, strictly adhering to GDPR constraints regarding PII.
- **Image Gallery:** Real-time streamed gallery (using `LazyVerticalGrid` and Coil memory-cache only) for flat photos or verification images.
- **Granular Profile Management:** Allowing girls to set basic rules or unavailability without storing sensitive data on-device.

---

## Phase 3: Future Ideas (Backlog)

- Integration of an automated background check or ID verification API.
- Automated temporary revocation of hardware keys if a device is reported lost.
- Biometric re-authentication (FaceID/TouchID) specifically before opening sensitive chats or reports.
