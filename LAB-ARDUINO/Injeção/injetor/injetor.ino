/*
  ================================================================================
  INJETOR MULTISSINAL AUTOMOTIVO COMPLETO - 28 SINAIS (CKP + CMP / HALL + INDUTIVO)
  ================================================================================
  
  Desenvolvido para Arduino UNO / Nano com Display LCD Keypad Shield.
  Gerador de sinais de Rotação (CKP - Hall e Indutivo) e Fase (CMP) para testes
  e simulação de centrais de injeção eletrônica (ECUs) em bancada.

  ================================================================================
  MAPEAMENTO DE PINOS E HARDWARE
  ================================================================================
  - Display LCD Shield 16x2: Pinos D8 (RS), D9 (E), D4 (D4), D5 (D5), D6 (D6), D7 (D7)
  - Teclado do Shield: Pino Analógico A0
  - Controle de Potenciômetro (Opcional): Pino Analógico A1
  
  - Saída Digital Hall (CKP 0-5V): Pino Digital D11
  - Saída Digital Indutivo CKP+ (Fase Positiva): Pino Digital D13
  - Saída Digital Indutivo CKP- (Fase Negativa Invertida): Pino Digital D12
  - Saída Digital Sinal de Fase (CMP 0-5V): Pino Digital D3

  ================================================================================
  ALIMENTAÇÃO E ATERRAMENTO - IMPORTANTE
  ================================================================================
  1) Sinal Indutivo (CKP): Alimentar o dispositivo com fonte AUTÔNOMA (9V a 15V).
     NÃO aterrar no veículo/bancada. Use os dois fios do sinal indutivo (D13 e D12).
  2) Sinal Hall (CKP/CMP): Pode ser alimentado pela bancada ou bateria. O terra (GND)
     do dispositivo DEVE ser interligado ao GND da central eletrônica / bancada.

  ================================================================================
  BIBLIOTECAS NECESSÁRIAS
  ================================================================================
  - LiquidCrystal.h (Padrão da IDE Arduino)
  - TimerOne.h (Instalar via Gerenciador de Bibliotecas)
  ================================================================================
*/

#include <Arduino.h>
#include <LiquidCrystal.h>
#include <TimerOne.h>

// Definicao dos Pinos
#define PIN_HALL      11  // Saída Hall CKP
#define PIN_IND_NEG   12  // Saída Indutiva CKP-
#define PIN_IND_POS   13  // Saída Indutiva CKP+
#define PIN_FASE      3   // Saída Sinal de Fase CMP
#define PIN_TECLADO   A0  // Teclado LCD Shield
#define PIN_POT       A1  // Potenciômetro opcional de RPM

// Inicializacao do Display LCD Shield
LiquidCrystal lcd(8, 9, 4, 5, 6, 7);

// Estrutura de dados para perfil de cada sinal
struct PerfilSinal {
  uint8_t id;
  const char* nome;
  uint8_t dentes_totais; // Dentes totais (incluindo falha)
  uint8_t falhas;        // Dentes de falha (gap)
  uint8_t tipo_fase;     // Tipo do sinal de fase
};

// Catálogo dos 28 Sinais Automotivos
const PerfilSinal PROGMEM CATALAGO_SINAIS[28] = {
  // SINAIS SIMPLES (1 a 14) - Submenu 1
  { 1,  "60-2 Universal", 60, 2, 0},
  { 2,  "36-1 Ford/Zetec", 36, 1, 0},
  { 3,  "36-2 Toyota/Ford",36, 2, 0},
  { 4,  "44-4 Fiat Fire",  44, 4, 0},
  { 5,  "30-2 Peugeot",   30, 2, 0},
  { 6,  "36-4 Nissan",    36, 4, 0},
  { 7,  "40-2 Hyundai",   40, 2, 0},
  { 8,  "12-1 Toyota 4A", 12, 1, 0},
  { 9,  "12-2 Subaru",    12, 2, 0},
  {10,  "8-1 Industrial",  8, 1, 0},
  {11,  "4-1 MWM/Gerador", 4, 1, 0},
  {12,  "DIST 4 Cyl",      4, 0, 0},
  {13,  "DIST 6 Cyl",      6, 0, 0},
  {14,  "DIST 8 Cyl",      8, 0, 0},

  // SINAIS SINCRONIZADOS COM FASE (15 a 28) - Submenu 2
  {15,  "60-2 + Fase 1P", 60, 2, 1},
  {16,  "36-1 + Fase 1P", 36, 1, 1},
  {17,  "36-2 + Fase 1P", 36, 2, 1},
  {18,  "44-4 + Fase 1P", 44, 4, 1},
  {19,  "VW EA111 4J",    60, 2, 2},
  {20,  "Fiat Firefly 3P",60, 2, 3},
  {21,  "GM Speeds 4P",   60, 2, 4},
  {22,  "Renault K4M 44-2",44, 4, 5},
  {23,  "Ford Duratec 5P",36, 1, 6},
  {24,  "Hyundai HB20 D", 60, 2, 7},
  {25,  "Honda Civic 24-1",24, 1, 1},
  {26,  "Toyota VVTi Dual",36, 2, 8},
  {27,  "Mitsub L200 4-2", 4, 2, 9},
  {28,  "Subaru Boxer 3P",36, 2, 10}
};

// Variaveis de Controle Global
volatile int start = 0;         // 1 = Gerando sinal, 0 = Parado
volatile int contador = 0;      // Contador de meio-dente da interrupção
volatile int cont_virab = 1;    // 1 ou 2 (Volta do virabrequim)
volatile int idx_sinal = 0;     // Índice do sinal ativo (0 a 27)

int submenu = 0;                // 0 = Principal, 1 = Simples, 2 = Sincronizados
int opc_menu = 0;               // Opção visual no menu principal
int key = 0;                    // Código da tecla pressionada
float rot_por_minuto = 1000;    // RPM padrão inicial
float tempo_us = 0;             // Período de timer em microssegundos

// Protótipos de Funções
void teclado();
void reset_default();
void menu_principal();
void menu_sinais();
void velocidade();
void roda_fonica();
void carregar_perfil(int index, PerfilSinal &p);

// --------------------------------------------------
// Função para carregar perfil da memória Flash (PROGMEM)
// --------------------------------------------------
void carregar_perfil(int index, PerfilSinal &p) {
  memcpy_P(&p, &CATALAGO_SINAIS[index], sizeof(PerfilSinal));
}

// --------------------------------------------------
// Leitura de Teclas do LCD Keypad Shield (Pino A0)
// --------------------------------------------------
void teclado() {
  int adc = analogRead(PIN_TECLADO);
  if (adc > 1000)      key = 0; // Nenhum botão
  else if (adc < 50)   key = 1; // RIGHT
  else if (adc < 195)  key = 2; // UP
  else if (adc < 380)  key = 3; // DOWN
  else if (adc < 555)  key = 4; // LEFT
  else if (adc < 790)  key = 5; // SELECT
  else                 key = 0;
}

// --------------------------------------------------
// Reset Geral / Parada de Saídas
// --------------------------------------------------
void reset_default() {
  delay(150);
  start = 0;
  digitalWrite(PIN_HALL, LOW);
  digitalWrite(PIN_IND_NEG, LOW);
  digitalWrite(PIN_IND_POS, LOW);
  digitalWrite(PIN_FASE, LOW);
  contador = 0;
  cont_virab = 1;
  lcd.clear();
  key = 0;
}

// --------------------------------------------------
// Setup de Inicialização do Arduino
// --------------------------------------------------
void setup() {
  lcd.begin(16, 2);

  pinMode(PIN_HALL, OUTPUT);
  pinMode(PIN_IND_NEG, OUTPUT);
  pinMode(PIN_IND_POS, OUTPUT);
  pinMode(PIN_FASE, OUTPUT);

  // Splash Screen de Inicialização
  lcd.setCursor(0, 0);
  lcd.print("Injetor Sinal ECU");
  lcd.setCursor(0, 1);
  lcd.print("28 Sinais CKP/CMP");
  delay(2000);

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("H = Sinal Hall");
  lcd.setCursor(0, 1);
  lcd.print("I = Indutivo CKP");
  delay(1500);
  lcd.clear();

  // Configuração Inicial do Timer1 (Interrupt de Roda Fônica)
  Timer1.initialize(200000);
  Timer1.attachInterrupt(roda_fonica);
}

// --------------------------------------------------
// Loop Principal
// --------------------------------------------------
void loop() {
  if (submenu == 0) {
    menu_principal();
  } else if (submenu == 1 || submenu == 2) {
    menu_sinais();
  }
}

// --------------------------------------------------
// Menu Principal: Escolha entre Simples e Sincronizados
// --------------------------------------------------
void menu_principal() {
  while (submenu == 0) {
    digitalWrite(PIN_HALL, LOW);
    digitalWrite(PIN_IND_NEG, LOW);
    digitalWrite(PIN_IND_POS, LOW);
    digitalWrite(PIN_FASE, LOW);

    if (opc_menu == 0) {
      lcd.setCursor(0, 0);
      lcd.print(">[1]SinaisSimples");
      lcd.setCursor(0, 1);
      lcd.print(" [2]Com Fase/Sinc");
    } else {
      lcd.setCursor(0, 0);
      lcd.print(" [1]SinaisSimples");
      lcd.setCursor(0, 1);
      lcd.print(">[2]Com Fase/Sinc");
    }

    teclado();

    if (key == 2 || key == 3) { // UP ou DOWN
      opc_menu = (opc_menu == 0) ? 1 : 0;
      delay(200);
      key = 0;
    }
    else if (key == 5) { // SELECT
      delay(200);
      lcd.clear();
      submenu = (opc_menu == 0) ? 1 : 2;
      idx_sinal = (submenu == 1) ? 0 : 14; // Começa do primeiro da categoria
      key = 0;
    }
  }
}

// --------------------------------------------------
// Submenu de Seleção dos Sinais (1 a 14 ou 15 a 28)
// --------------------------------------------------
void menu_sinais() {
  int min_idx = (submenu == 1) ? 0 : 14;
  int max_idx = (submenu == 1) ? 13 : 27;

  if (idx_sinal < min_idx || idx_sinal > max_idx) {
    idx_sinal = min_idx;
  }

  PerfilSinal p;

  while (submenu == 1 || submenu == 2) {
    carregar_perfil(idx_sinal, p);

    lcd.setCursor(0, 0);
    if (p.id < 10) lcd.print("0");
    lcd.print(p.id);
    lcd.print("/");
    lcd.print("28 ");
    lcd.print(p.nome);
    // Limpa final da linha no LCD
    for (int i = strlen(p.nome) + 6; i < 16; i++) lcd.print(" ");

    lcd.setCursor(0, 1);
    lcd.print("U/D:Sel  SEL:Gera");

    teclado();

    if (key == 2) { // UP: Sinal anterior
      delay(200);
      idx_sinal--;
      if (idx_sinal < min_idx) idx_sinal = max_idx;
      key = 0;
      lcd.clear();
    }
    else if (key == 3) { // DOWN: Próximo sinal
      delay(200);
      idx_sinal++;
      if (idx_sinal > max_idx) idx_sinal = min_idx;
      key = 0;
      lcd.clear();
    }
    else if (key == 5) { // SELECT: Inicia geração do sinal
      delay(200);
      start = 1;
      key = 0;
      velocidade(); // Entra na tela de geração
      lcd.clear();
    }
    else if (key == 4) { // LEFT: Volta ao menu principal
      delay(200);
      submenu = 0;
      reset_default();
      return;
    }
  }
}

// --------------------------------------------------
// Loop de Geracao e Ajuste de Rotação (RPM)
// --------------------------------------------------
void velocidade() {
  PerfilSinal p;
  carregar_perfil(idx_sinal, p);

  if (rot_por_minuto < 300) rot_por_minuto = 1000;

  // Cálculo da frequência do timer em microssegundos
  // tempo_us = (30.000.000) / (RPM * dentes_totais)
  tempo_us = (30000000.0) / (rot_por_minuto * (float)p.dentes_totais);
  Timer1.setPeriod((long)tempo_us);

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print(p.nome);
  
  lcd.setCursor(0, 1);
  lcd.print((int)rot_por_minuto);
  lcd.print("RPM SEL:Sair");

  unsigned long ultimo_update = millis();

  while (start == 1) {
    teclado();
    bool mudou_rpm = false;

    if (key == 2) { // UP: +250 RPM
      rot_por_minuto += 250;
      if (rot_por_minuto > 7000) rot_por_minuto = 7000;
      mudou_rpm = true;
      delay(150);
    }
    else if (key == 3) { // DOWN: -250 RPM
      rot_por_minuto -= 250;
      if (rot_por_minuto < 300) rot_por_minuto = 300;
      mudou_rpm = true;
      delay(150);
    }
    else if (key == 1) { // RIGHT: +1000 RPM
      rot_por_minuto += 1000;
      if (rot_por_minuto > 7000) rot_por_minuto = 7000;
      mudou_rpm = true;
      delay(150);
    }
    else if (key == 4) { // LEFT: -1000 RPM
      rot_por_minuto -= 1000;
      if (rot_por_minuto < 300) rot_por_minuto = 300;
      mudou_rpm = true;
      delay(150);
    }
    else if (key == 5) { // SELECT: Sair e Parar Sinal
      reset_default();
      return;
    }

    // Leitura opcional de potenciometro analógico em A1
    int pot = analogRead(PIN_POT);
    if (pot > 30 && pot < 1000) {
      static int pot_ant = 0;
      if (abs(pot - pot_ant) > 15) {
        pot_ant = pot;
        rot_por_minuto = map(pot, 0, 1023, 300, 7000);
        mudou_rpm = true;
      }
    }

    // Atualiza frequência do Timer se RPM mudou
    if (mudou_rpm) {
      tempo_us = (30000000.0) / (rot_por_minuto * (float)p.dentes_totais);
      Timer1.setPeriod((long)tempo_us);

      lcd.setCursor(0, 1);
      lcd.print("                ");
      lcd.setCursor(0, 1);
      lcd.print((int)rot_por_minuto);
      lcd.print("RPM SEL:Sair");
    }

    // Refresh periódico do LCD
    if (millis() - ultimo_update > 500) {
      ultimo_update = millis();
      lcd.setCursor(0, 1);
      lcd.print((int)rot_por_minuto);
      lcd.print("RPM SEL:Sair ");
    }
  }

  reset_default();
}

// --------------------------------------------------
// Interrupcao de Timer1 - Geracao dos Pulsos CKP/CMP
// --------------------------------------------------
void roda_fonica() {
  if (start == 0) {
    digitalWrite(PIN_HALL, LOW);
    digitalWrite(PIN_IND_NEG, LOW);
    digitalWrite(PIN_IND_POS, LOW);
    digitalWrite(PIN_FASE, LOW);
    contador = 0;
    return;
  }

  PerfilSinal p;
  carregar_perfil(idx_sinal, p);

  int passos_totais = p.dentes_totais * 2; // 2 meio-dentes por dente
  contador++;

  if (contador > passos_totais) {
    contador = 1;
    cont_virab++;
    if (cont_virab > 2) {
      cont_virab = 1;
    }
  }

  // -------------------------------------------------
  // 1) GERAÇÃO DO SINAL DE ROTAÇÃO (CKP - HALL E INDUTIVO)
  // -------------------------------------------------
  int passos_ativos = (p.dentes_totais - p.falhas) * 2;

  if (contador <= passos_ativos) {
    if (contador % 2 != 0) {
      // Meio dente Nível Alto
      digitalWrite(PIN_HALL, HIGH);    // Hall 0-5V
      digitalWrite(PIN_IND_POS, HIGH); // Indutivo CKP+
      digitalWrite(PIN_IND_NEG, LOW);  // Indutivo CKP-
    } else {
      // Meio dente Nível Baixo
      digitalWrite(PIN_HALL, LOW);     // Hall 0-5V
      digitalWrite(PIN_IND_POS, LOW);  // Indutivo CKP+
      digitalWrite(PIN_IND_NEG, HIGH); // Indutivo CKP-
    }
  } else {
    // Gap / Falha de Dentes
    digitalWrite(PIN_HALL, LOW);
    digitalWrite(PIN_IND_POS, LOW);
    digitalWrite(PIN_IND_NEG, LOW);
  }

  // -------------------------------------------------
  // 2) GERAÇÃO DO SINAL DE FASE (CMP - PINO D3)
  // -------------------------------------------------
  bool fase_state = false;

  switch (p.tipo_fase) {
    case 0:
      // Sem Sinal de Fase
      fase_state = false;
      break;

    case 1:
      // 1 Pulso simples na 1ª volta do virabrequim (Dente 1)
      if (cont_virab == 1 && contador <= 2) {
        fase_state = true;
      }
      break;

    case 2:
      // VW EA111 (60-2 com Fase 4 Janelas Assimétricas em 2 voltas)
      if (cont_virab == 1) {
        if ((contador >= 1 && contador <= 20) || (contador >= 50 && contador <= 80)) {
          fase_state = true;
        }
      } else { // cont_virab == 2
        if ((contador >= 10 && contador <= 40) || (contador >= 70 && contador <= 100)) {
          fase_state = true;
        }
      }
      break;

    case 3:
      // Fiat Firefly (3 Pulsos por ciclo de fase)
      if (cont_virab == 1) {
        if ((contador >= 1 && contador <= 4) || (contador >= 20 && contador <= 24)) {
          fase_state = true;
        }
      } else {
        if (contador >= 40 && contador <= 44) {
          fase_state = true;
        }
      }
      break;

    case 4:
      // GM Speeds SPE/4 (4 Pulsos distribuidos)
      if ((contador >= 1 && contador <= 4) || (contador >= 30 && contador <= 34)) {
        fase_state = true;
      }
      break;

    case 5:
      // Renault K4M (44-2-2 com Fase)
      if (cont_virab == 1 && contador >= 1 && contador <= 8) {
        fase_state = true;
      }
      break;

    case 6:
      // Ford Duratec/Sigma (5 Pulsos assimétricos)
      if (cont_virab == 1) {
        if ((contador >= 1 && contador <= 6) || (contador >= 20 && contador <= 26)) {
          fase_state = true;
        }
      } else {
        if ((contador >= 10 && contador <= 16) || (contador >= 35 && contador <= 40)) {
          fase_state = true;
        }
      }
      break;

    case 7:
      // Hyundai HB20 (Dual Phase Pattern)
      if (cont_virab == 1 && contador >= 1 && contador <= 12) {
        fase_state = true;
      } else if (cont_virab == 2 && contador >= 30 && contador <= 42) {
        fase_state = true;
      }
      break;

    case 8:
      // Toyota Corolla VVTi Dual
      if (cont_virab == 1 && ((contador >= 1 && contador <= 4) || (contador >= 24 && contador <= 28))) {
        fase_state = true;
      }
      break;

    case 9:
      // Mitsubishi L200 (4-2 + Fase)
      if (cont_virab == 1 && contador <= 2) {
        fase_state = true;
      }
      break;

    case 10:
      // Subaru Boxer (36-2-2-2 + 3 Pulsos)
      if ((cont_virab == 1 && contador <= 4) || (cont_virab == 2 && contador >= 20 && contador <= 24)) {
        fase_state = true;
      }
      break;

    default:
      fase_state = false;
      break;
  }

  digitalWrite(PIN_FASE, fase_state ? HIGH : LOW);
}
