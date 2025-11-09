// ===============================================
//  PROGRAM ABSENSI RFID ONLINE & OFFLINE ESP32
//  - Membaca UID kartu RFID (MFRC522)
//  - Mengirim data absensi ke server via HTTP POST JSON
//  - Menyimpan data offline jika internet mati (SPIFFS)
//  - Sinkron otomatis saat koneksi kembali
// ===============================================

#include <WiFi.h>
#include <HTTPClient.h>
#include <SPI.h>
#include <MFRC522.h>
#include <SPIFFS.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>

// Pin koneksi RC522 ke WEMOS
#define SS_PIN 5   // SDA
#define RST_PIN 22 // RST

MFRC522 rfid(SS_PIN, RST_PIN);
LiquidCrystal_I2C lcd(0x27, 16, 2); // LCD 16x2

// WiFi
const char *ssid = "NAMA_WIFI_KAMU";
const char *password = "PASSWORD_WIFI_KAMU";

// Server API
const char *serverUrl = "https://smkdarulamalkotametro.sch.id/app/rekam_absen_api.php";
const char *api_key = "API_KEY_HARDWARE_KAMU";

unsigned long lastCheck = 0;
bool wifiConnected = false;

// =============================
// SETUP
// =============================
void setup()
{
    Serial.begin(115200);
    SPI.begin();
    rfid.PCD_Init();

    Wire.begin(21, 22);
    lcd.init();
    lcd.backlight();

    // 🔹 LCD STATUS: saat mulai
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Inisialisasi...");
    delay(1000);

    // Inisialisasi SPIFFS
    if (!SPIFFS.begin(true))
    {
        Serial.println("⚠️ Gagal inisialisasi SPIFFS!");
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("Gagal SPIFFS");
        while (1);
    }

    connectWiFi();

    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Siap Scan Kartu");
    Serial.println("🔹 Siap membaca kartu RFID...");
}

// =============================
// LOOP
// =============================
void loop()
{
    // Cek WiFi tiap 10 detik
    if (millis() - lastCheck > 10000)
    {
        if (WiFi.status() != WL_CONNECTED)
        {
            wifiConnected = false;
            connectWiFi();
        }
        else
        {
            wifiConnected = true;
            sendOfflineData();
        }
        lastCheck = millis();
    }

    if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial())
        return;

    String uid = "";
    for (byte i = 0; i < rfid.uid.size; i++)
    {
        uid += String(rfid.uid.uidByte[i], HEX);
    }
    uid.toUpperCase();

    Serial.println("🎫 UID Kartu: " + uid);

    // 🔹 LCD STATUS: tampilkan UID
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Kartu:");
    lcd.setCursor(0, 1);
    lcd.print(uid);

    String jsonData = "{\"api_key\":\"" + String(api_key) + "\",\"uid\":\"" + uid + "\"}";

    if (wifiConnected)
    {
        if (sendData(jsonData))
        {
            Serial.println("✅ Data terkirim ke server!");
            lcd.clear();
            lcd.setCursor(0, 0);
            lcd.print("Terkirim ke");
            lcd.setCursor(0, 1);
            lcd.print("Server");
        }
        else
        {
            saveOfflineData(jsonData);
        }
    }
    else
    {
        saveOfflineData(jsonData);
    }

    delay(2000);
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Siap Scan Lagi");
}

// =============================
// 📶 KONEKSI WIFI
// =============================
void connectWiFi()
{
    Serial.print("🔄 Menghubungkan WiFi ");
    Serial.println(ssid);

    // 🔹 LCD STATUS: koneksi WiFi
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Menghub WiFi...");
    lcd.setCursor(0, 1);
    lcd.print(ssid);

    WiFi.begin(ssid, password);
    int retry = 0;

    while (WiFi.status() != WL_CONNECTED && retry < 15)
    {
        delay(1000);
        Serial.print(".");
        retry++;
    }

    if (WiFi.status() == WL_CONNECTED)
    {
        Serial.println("\n✅ Terhubung ke WiFi!");
        Serial.println(WiFi.localIP());
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("WiFi Terhubung!");
        lcd.setCursor(0, 1);
        lcd.print(WiFi.localIP().toString());
        wifiConnected = true;
        delay(1500);
    }
    else
    {
        Serial.println("\n❌ Gagal konek WiFi.");
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("WiFi Gagal");
        lcd.setCursor(0, 1);
        lcd.print("Offline Mode");
        wifiConnected = false;
        delay(1500);
    }
}

// =============================
// 🚀 KIRIM DATA KE SERVER
// =============================
bool sendData(String jsonData)
{
    if (WiFi.status() != WL_CONNECTED)
        return false;

    HTTPClient http;
    http.begin(serverUrl);
    http.addHeader("Content-Type", "application/json");

    // 🔹 LCD STATUS: kirim data
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Kirim Data...");
    lcd.setCursor(0, 1);
    lcd.print("Ke Server...");

    int httpCode = http.POST(jsonData);
    if (httpCode > 0)
    {
        String response = http.getString();
        Serial.println("📡 Respon Server: " + response);
        http.end();

        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("Server OK!");
        delay(1000);
        return true;
    }
    else
    {
        Serial.println("⚠️ Gagal kirim data: " + String(http.errorToString(httpCode)));
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("Gagal Kirim!");
        lcd.setCursor(0, 1);
        lcd.print("Simpan Offline");
        delay(1000);
        http.end();
        return false;
    }
}

// =============================
// 💾 SIMPAN DATA OFFLINE
// =============================
void saveOfflineData(String data)
{
    File file = SPIFFS.open("/offline.txt", FILE_APPEND);
    if (!file)
    {
        Serial.println("⚠️ Gagal buka file offline!");
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("Err File Offline");
        return;
    }
    file.println(data);
    file.close();
    Serial.println("💾 Data disimpan offline!");
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Data Offline");
    lcd.setCursor(0, 1);
    lcd.print("Tersimpan!");
    delay(1000);
}

// =============================
// 🔁 KIRIM DATA OFFLINE
// =============================
void sendOfflineData()
{
    File file = SPIFFS.open("/offline.txt", FILE_READ);
    if (!file || file.size() == 0)
    {
        file.close();
        return;
    }

    Serial.println("🔁 Mengirim data offline...");

    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Sinkronisasi...");
    lcd.setCursor(0, 1);
    lcd.print("Data Offline");

    String newData = "";
    while (file.available())
    {
        String line = file.readStringUntil('\n');
        line.trim();
        if (line.length() > 0)
        {
            if (!sendData(line))
            {
                newData += line + "\n";
            }
        }
    }
    file.close();

    SPIFFS.remove("/offline.txt");

    if (newData.length() > 0)
    {
        File f = SPIFFS.open("/offline.txt", FILE_WRITE);
        f.print(newData);
        f.close();
    }

    Serial.println("✅ Sinkronisasi selesai!");
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Sinkron Selesai!");
    delay(1000);
}