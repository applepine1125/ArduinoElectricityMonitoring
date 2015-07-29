#include<stdlib.h>
#include <SoftwareSerial.h>
#define SSID "[SSID]"
#define PASS "[Pass]"
#define IP "184.106.153.149" // thingspeak.com
String GET = "GET /update?key=[APIKey]&field1=";
SoftwareSerial monitor(0, 1); // RX, TX

int aveN = 600; //average time 600で 1 scan/15秒程度
int sumN = 100; // average time, 100回の平均
float Coeff = 64.453125; //センサーが出力する電流量(A,アンペア)から電力(W,ワット)を求める係数。
float sensV0 = 0;  // variable to store the value coming from the sensor
float sensV0ave = 0;  // variable to store the value coming from the sensor
float SumW = 0; //total Voltage
float Read0 = 0; //読み取った値0

void setup()
{
  monitor.begin(9600);
  Serial.begin(9600);
  analogReference(INTERNAL);
  sendDebug("AT");
  delay(5000);
  if (Serial.find("OK")) {
    monitor.println("RECEIVED: OK");
    connectWiFi();
  }
}

void loop() {
  sensV0ave = 0; //600回平均を100回平均するための値の初期化
  for (int j = 0; j < aveN; j++) {
    sensV0 = 0; //600回の平均を取る値の初期化
    //100回の平均を取る。
    for (int i = 0; i < sumN; i++) {
      Read0 = (float) analogRead(0); //Analog INの0番端子の電圧を読む。
      sensV0 = sensV0 + Read0 * Read0 / sumN; //二乗の和をとる。電力は電流の二乗に比例。
    }
    sensV0ave = sensV0ave + Coeff * sqrt(sensV0) / aveN; //600回計測して平均値を求める。
  }
  SumW = sensV0ave; //200Vでは2本の端子の和で総電力量となる。
  char buffer[10];
  String SumWF = dtostrf(SumW, 4, 1, buffer);
  updatewatt(SumWF);
  delay(1000);
}

void updatewatt(String SumWF) {
  String cmd = "AT+CIPSTART=\"TCP\",\"";
  cmd += IP;
  cmd += "\",80";
  sendDebug(cmd);
  delay(2000);
  if (Serial.find("Error")) {
    monitor.print("RECEIVED: Error");
    return;
  }
  cmd = GET;
  cmd += SumWF;
  cmd += "\r\n";
  Serial.print("AT+CIPSEND=");
  Serial.println(cmd.length());
  if (Serial.find(">")) {
    monitor.print(">");
    monitor.print(cmd);
    Serial.print(cmd);
  } else {
    sendDebug("AT+CIPCLOSE");
  }
  if (Serial.find("OK")) {
    monitor.println("RECEIVED: OK");
  } else {
    monitor.println("RECEIVED: Error");
  }
}
void sendDebug(String cmd) {
  monitor.print("SEND: ");
  monitor.println(cmd);
  Serial.println(cmd);
}

boolean connectWiFi() {
  Serial.println("AT+CWMODE=1");
  delay(2000);
  String cmd = "AT+CWJAP=\"";
  cmd += SSID;
  cmd += "\",\"";
  cmd += PASS;
  cmd += "\"";
  sendDebug(cmd);
  delay(5000);
  if (Serial.find("OK")) {
    monitor.println("RECEIVED: OK");
    return true;
  } else {
    monitor.println("RECEIVED: Error");
    return false;
  }
}
