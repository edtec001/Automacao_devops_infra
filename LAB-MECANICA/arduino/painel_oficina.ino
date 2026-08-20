/*
 * Painel de Controle da Oficina Mecanica - Arduino Firmware
 * 
 * LEDs de Status (Piscantes):
 * - LED Vermelho (Pino 2): Entrada do Veiculo
 * - LED Amarelo  (Pino 3): Aguardando Orcamento
 * - LED Azul     (Pino 4): Em Servico
 * - LED Verde    (Pino 5): Liberado para Entrega
 */

const int PIN_LED_VERMELHO = 2;
const int PIN_LED_AMARELO  = 3;
const int PIN_LED_AZUL     = 4;
const int PIN_LED_VERDE    = 5;

String inputBuffer = "";

// Variaveis para controle de pisca nao-bloqueante (millis)
int pinoLedAtivo = -1;
unsigned long tempoAnterior = 0;
const long intervaloPisca = 500; // 500ms ligado, 500ms desligado
bool estadoLed = false;

void desligarTodosLEDs() {
  digitalWrite(PIN_LED_VERMELHO, LOW);
  digitalWrite(PIN_LED_AMARELO, LOW);
  digitalWrite(PIN_LED_AZUL, LOW);
  digitalWrite(PIN_LED_VERDE, LOW);
}

void setup() {
  Serial.begin(9600);

  pinMode(PIN_LED_VERMELHO, OUTPUT);
  pinMode(PIN_LED_AMARELO, OUTPUT);
  pinMode(PIN_LED_AZUL, OUTPUT);
  pinMode(PIN_LED_VERDE, OUTPUT);

  // Teste de inicializacao dos LEDs
  desligarTodosLEDs();
  digitalWrite(PIN_LED_VERMELHO, HIGH); delay(200);
  digitalWrite(PIN_LED_AMARELO, HIGH);  delay(200);
  digitalWrite(PIN_LED_AZUL, HIGH);     delay(200);
  digitalWrite(PIN_LED_VERDE, HIGH);    delay(200);
  desligarTodosLEDs();

  Serial.println("ARDUINO_OK: Painel da oficina pronto (modo pisca ativo).");
}

void processarComando(String cmdString) {
  cmdString.trim();
  if (cmdString.length() == 0) return;

  // Formato esperado: PLACA:STATUS:COMANDO (ex: ABC1D23:ENTRADA:VERMELHO)
  int firstIndex = cmdString.indexOf(':');
  int secondIndex = cmdString.indexOf(':', firstIndex + 1);

  String placa = "";
  String status = "";
  String comando = "";

  if (firstIndex != -1 && secondIndex != -1) {
    placa = cmdString.substring(0, firstIndex);
    status = cmdString.substring(firstIndex + 1, secondIndex);
    comando = cmdString.substring(secondIndex + 1);
  } else {
    comando = cmdString;
  }

  comando.toUpperCase();
  status.toUpperCase();

  Serial.print("Executando para ");
  Serial.print(placa);
  Serial.print(" -> Comando: ");
  Serial.println(comando);

  desligarTodosLEDs();

  if (comando == "VERMELHO" || status == "ENTRADA") {
    pinoLedAtivo = PIN_LED_VERMELHO;
  } else if (comando == "AMARELO" || status == "ORCAMENTO") {
    pinoLedAtivo = PIN_LED_AMARELO;
  } else if (comando == "AZUL" || status == "SERVICO") {
    pinoLedAtivo = PIN_LED_AZUL;
  } else if (comando == "VERDE" || status == "LIBERADO") {
    pinoLedAtivo = PIN_LED_VERDE;
  } else if (comando == "DESLIGAR" || comando == "SAIDA") {
    pinoLedAtivo = -1;
  }

  estadoLed = false;
  tempoAnterior = 0;
}

void loop() {
  // Leitura da porta Serial (nao-bloqueante)
  while (Serial.available() > 0) {
    char inChar = (char)Serial.read();
    if (inChar == '\n') {
      processarComando(inputBuffer);
      inputBuffer = "";
    } else if (inChar != '\r') {
      inputBuffer += inChar;
    }
  }

  // Lógica de pisca do LED ativo usando millis()
  if (pinoLedAtivo != -1) {
    unsigned long tempoAtual = millis();
    if (tempoAtual - tempoAnterior >= intervaloPisca) {
      tempoAnterior = tempoAtual;
      estadoLed = !estadoLed;
      desligarTodosLEDs();
      digitalWrite(pinoLedAtivo, estadoLed ? HIGH : LOW);
    }
  } else {
    desligarTodosLEDs();
  }
}

