// ===============================================
//  PROGRAM ABSENSI RFID ONLINE & OFFLINE (ESP8266)
//  - Membaca UID kartu RFID (MFRC522)
//  - Kirim ke server via HTTP POST JSON
//  - Simpan data offline ke SD Card jika gagal
//  - Sinkron otomatis saat koneksi kembali
// ===============================================

#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <SPI.h>
#include <MFRC522.h>
#include <SD.h>

// 🔹 Koneksi RC522 ke WEMOS D1 mini (disarankan)
#define RST_PIN D3   // RST
#define SS_PIN  D4   // SDA/SS
#define SS_SD   D8   // CS SD card (gunakan D8 untuk SD)

// Inisialisasi RFID
MFRC522 rfid(SS_PIN, RST_PIN);

// WiFi
const char *ssid = "JEFFRI";
const char *password = "apinarip02";

// Server API
const char *serverUrl = "https://smkdarulamalkotametro.sch.id/app/rekam_absen_api.php";
const char *api_key = "API_KEY_HARDWARE_KAMU";

// Variabel status
unsigned long lastCheck = 0;
bool wifiConnected = false;

// =============================
// SETUP
// =============================
void setup() {
  Serial.begin(115200);
  SPI.begin();

  // Inisialisasi RFID
  rfid.PCD_Init();
  delay(100);
  Serial.println("🔹 Inisialisasi sistem...");

  // Inisialisasi SD card
  if (!SD.begin(SS_SD)) {
    Serial.println("❌ SD Card gagal diinisialisasi!");
  } else {
    Serial.println("💾 SD Card siap digunakan.");
  }

  // Koneksi WiFi
  connectWiFi();

  Serial.println("✅ Siap membaca kartu RFID...");
}

// =============================
// LOOP
// =============================
void loop() {
  // Cek koneksi WiFi tiap 10 detik
  if (millis() - lastCheck > 10000) {
    if (WiFi.status() != WL_CONNECTED) {
      wifiConnected = false;
      connectWiFi();
    } else {
      wifiConnected = true;
      sendOfflineData();
    }
    lastCheck = millis();
  }

  // Cek kartu RFID
  if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial())
    return;

  String uid = "";
  for (byte i = 0; i < rfid.uid.size; i++) {
    uid += String(rfid.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();

  Serial.println("🎫 UID Kartu: " + uid);

  // Buat data JSON
  String jsonData = "{\"api_key\":\"" + String(api_key) + "\",\"uid\":\"" + uid + "\"}";

  // Kirim data
  if (wifiConnected) {
    if (sendData(jsonData)) {
      Serial.println("✅ Data terkirim ke server!");
    } else {
      saveOfflineData(jsonData);
    }
  } else {
    saveOfflineData(jsonData);
  }

  delay(2000);
}

// =============================
// 📶 KONEKSI WIFI
// =============================
void connectWiFi() {
  Serial.print("🔄 Menghubungkan ke WiFi: ");
  Serial.println(ssid);

  WiFi.begin(ssid, password);
  int retry = 0;

  while (WiFi.status() != WL_CONNECTED && retry < 20) {
    delay(1000);
    Serial.print(".");
    retry++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n✅ Terhubung ke WiFi!");
    Serial.print("📡 IP: ");
    Serial.println(WiFi.localIP());
    wifiConnected = true;
  } else {
    Serial.println("\n❌ Gagal konek WiFi. Mode Offline.");
    wifiConnected = false;
  }
}

// =============================
// 🚀 KIRIM DATA KE SERVER
// =============================
bool sendData(String jsonData) {
  if (WiFi.status() != WL_CONNECTED)
    return false;

  WiFiClient client;
  HTTPClient http;

  Serial.println("📡 Mengirim data ke server...");

  if (!http.begin(client, serverUrl)) {
    Serial.println("⚠️ Gagal menginisialisasi HTTP client!");
    return false;
  }

  http.addHeader("Content-Type", "application/json");
  int httpCode = http.POST(jsonData);

  if (httpCode > 0) {
    String response = http.getString();
    Serial.println("📡 Respon Server: " + response);
    http.end();
    return true;
  } else {
    Serial.println("⚠️ Gagal kirim data: " + http.errorToString(httpCode));
    http.end();
    return false;
  }
}

// =============================
// 💾 SIMPAN DATA OFFLINE
// =============================
void saveOfflineData(String data) {
  File file = SD.open("/offline.txt", FILE_WRITE);
  if (!file) {
    Serial.println("⚠️ Gagal membuka file offline!");
    return;
  }
  file.println(data);
  file.close();
  Serial.println("💾 Data disimpan ke SD Card (offline)!");
}

// =============================
// 🔁 KIRIM DATA OFFLINE
// =============================
void sendOfflineData() {
  File file = SD.open("/offline.txt", FILE_READ);
  if (!file || file.size() == 0) {
    file.close();
    return;
  }

  Serial.println("🔁 Mengirim data offline...");

  String newData = "";
  while (file.available()) {
    String line = file.readStringUntil('\n');
    line.trim();
    if (line.length() > 0) {
      if (!sendData(line)) {
        newData += line + "\n";
      }
    }
  }
  file.close();

  SD.remove("/offline.txt");

  if (newData.length() > 0) {
    File f = SD.open("/offline.txt", FILE_WRITE);
    f.print(newData);
    f.close();
  }

  Serial.println("✅ Sinkronisasi data offline selesai!");
}
