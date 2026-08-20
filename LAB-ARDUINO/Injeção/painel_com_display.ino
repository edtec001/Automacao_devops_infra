/*
      ---------------------------------------------------
            PAINEL DE INSTRUMENTOS EXPERIMENTAL 
      Velocímetro - Tacômetro - Temperatura - Combustível
      
                        ROGÉRIO LEITE
                          Fev/2026
      ---------------------------------------------------
      
      Compilação e placa:
      Arduino AVR Boards > Arduino Nano.
      Ferramentas > Processador > ATmega328P (Old Bootloader)

*/

// -------------------------------------
// Construtor do display (Biblioteca U8g2)
#include <U8g2lib.h>
U8G2_ST7920_128X64_F_SW_SPI u8g2( U8G2_R0, 10, 11, 12, U8X8_PIN_NONE);

// ----------------------------------------- CONFIGURAÇÕES DO USUÁRIO  --------------------------------------------------------------------------------

uint16_t diametroPneu_mm = 561;         // Usado pelo Velocimetro: Pneu 165/70R13 = 561mm de diâmetro 
uint16_t ppv_roda = 4;                  // Usado pelo Velocimetro: 4 ppv (Com esses dados 63 Hz = 100 km/h)
byte numCilindros = 4;                  // Usado pelo Tacômetro  : Numero de cilindros do veículo (usado para o Tacômetro)      
float voltas_por_pulso = 2.0;           // Usado pelo Tacômetro: quant. de voltas do virabrequim por cada pulso unitário -  Para 1 bico, use 2.0 
                                        // Se usar sinal bobina ou distribuidor use (2.0 / numCilindros)
                                        // Se amanhã você decidir pegar o sinal do contagiros original do carro 
                                        // (que geralmente envia 2 pulsos por volta em um 4 cilindros), use ppv_taco = 0.5.
                                        // Em um motor 4 tempos, cada bico injetor pulsa apenas 1 vez a cada 2 voltas do virabrequim, ou seja 0.5 pulsos por volta.
const float R_FIXO_NTC = 1000.0;        // Resistor R3
const float R_FIXO = 220.0;             // Resistor R4


// Configuração do NTC 
// Mapeamento da resistência do NTC baseada nos sensores Magnet Marelli  MTE-Thompsom
const int numPontos = 4;                                   // Qty de pontos de calibração 
const float ohmsPontos[] = {2500.0, 580.0, 240.0, 110.0};  // Pontos de resistência (em Ohms)
const float tempPontos[] = {20.0,   60.0,  90.0,  120.0};  // versus temperatura (Celcius)


// Configuração do Sensor de Combustível
// Valores de resistência para tanque cheio, meio e vazio usando potenciômetro de 10K
// Comente as linhas abaixo para usar sensor real e use os valores reais
const float OHMS_CHEIO = 0.0;
const float OHMS_MEIO  = 5000.0;
const float OHMS_VAZIO = 10000.0;

// Valor real de resistência para tanque cheio, meio e vazio
//const float OHMS_CHEIO = 52.0;   
//const float OHMS_MEIO  = 175.0;
//const float OHMS_VAZIO = 418.0;


// ----------------------------------------- FIM DAS CONFIGURAÇÕES DO USUÁRIO  -----------------------------------------------------------------------



// ******************************************
// Configuração do Hardware (pinos e valores)
// ******************************************

const int PINO_VELO = 2;         // Pino do sensor Hall do velocímetro (digital)
const int PINO_TACO  = 3;        // Pino do sensor de RPM ligado ao bico injetor (digital)
const int PINO_FUEL = A5;        // Pino do sensor de combustivel (analog)
const int PINO_NTC= A0;          // Pino do sensor de temperatura (analog)



// Filtro de Média para o sensor de temperatura
unsigned long somaADC_NTC = 0;
int contador_NTC = 0;
const int AMOSTRAS_NTC = 10; // Quantidade de amostras para tirar a média
int temp_motor=10;           // Valor default da temperatura
float pct_comb=100.0;


// -------------------------------------
// --- Configuração do display  ---

#define ANG_ZERO  (210.0 * DEG_TO_RAD)   // Ângulo que inicia a agulha dos instrumentos
#define ANG_SWEEP (-240.0 * DEG_TO_RAD)  // Inversão do ângulo de início
#define VMAX 200                         // velocidade máxima Permitida
#define SPEED_SAMPLES 5                  // Quantidade de amostras para calcular a velocidade
#define RPM_MAX 5000                     // Máxima Rotação permitida
#define RPM_SAMPLES 5                    // QTY de amostras 


// --- Defaults iniciais do Velocímetro ---
float velocidade = 0;
byte speedIndex = 0;
float speedBuffer[SPEED_SAMPLES];

// --- Defaults iniciais do Tacômetro ---
volatile unsigned long ultimoSalto = 0;
volatile float frequenciaHz = 0;
float rpm = 0;
float rpmFiltered = 0.0;


// Geometria do velocimetro
int cx = 32;     
int cy = 32;
int radius_velo = 28;

// Geometria do Tacômetro
int cx_taco = 83;
int cy_taco = 23;
int radius_taco  = 19;



// ------------------ Funções de Mapeamento ---------------------

// >>> Função auxiliar de leitura de combustivel
float mapFloat(float x, float in_min, float in_max, float out_min, float out_max) {
  return (x - in_min) * (out_max - out_min) / (in_max - in_min) + out_min;
}

// >>> Função de cálculo por interpolação (Linearização da curva do NTC)
float calcularTemperatura(float r) {
  // Limites de segurança
  if (r >= ohmsPontos[0]) return tempPontos[0];
  if (r <= ohmsPontos[numPontos-1]) return tempPontos[numPontos-1];

  // Mapa de temperatura: Busca o intervalo correto na tabela
  for (int i = 0; i < numPontos - 1; i++) {
    if (r <= ohmsPontos[i] && r >= ohmsPontos[i+1]) {
      // Regra de 3 para o segmento da curva
      return (r - ohmsPontos[i]) * (tempPontos[i+1] - tempPontos[i]) / (ohmsPontos[i+1] - ohmsPontos[i]) + tempPontos[i];
    }
  }
  return 0;
}
// ---------------- FIM das Funções de Mapeamento --------------------

void debug_serial(){
// DEBUG
// Função usada para debugar. imprime valores no monitor serial
 
  Serial.print (round(velocidade));   
  Serial.print ("\t"); 
  Serial.print (round(rpm));   
  Serial.print ("\t");   
  Serial.print (round(pct_comb)); 
  Serial.print ("\t");   
  Serial.print (temp_motor);   
  Serial.println();
}



void setup() {
  u8g2.begin();
  Serial.begin(115200);
  
  pinMode(PINO_VELO, INPUT_PULLUP);                                               // Liga resistor interno de Pull UP (D2)
  attachInterrupt(digitalPinToInterrupt(PINO_VELO), calc_pulso_velo , RISING);    // Detecta os pulsos do opto ligado ao sensor Hall
  
  pinMode(PINO_TACO, INPUT_PULLUP);                                                 // Liga resistor interno de Pull UP (D3)
  attachInterrupt(digitalPinToInterrupt(PINO_TACO),   calc_pulso_rpm ,  RISING);  // Detecta os pulsos do opto ligado ao bico injetor
  
 }


void loop() {

// ---------------------------------------
// Atualiza os valores lidos pelo Hardware
// ---------------------------------------
  update_speed();
  update_rpm();
  update_combustivel();
  update_tempMotor();
  debug_serial(); // Debug de dados pela porta serial
 

// ---------------------------------------
// Renderiza os instrumentos
// ---------------------------------------

 static unsigned long lastDisplayTime = 0;
  unsigned long currentTime = millis();
  
  if (currentTime - lastDisplayTime >= 100) {
    lastDisplayTime = currentTime;
    
  u8g2.firstPage();
  do {
    desenha_velo();                   // Desenha o velocimetro
    desenha_tics_velo();              // Desenha marcas no velocimetro
    move_agulha_velo(velocidade);     // Desenha a posição da agulha de acordo com a velocidade
    
    desenha_taco();                   // Desenha o tacômetro    
    desenha_tics_taco();              // Desenhas as marcas no tacômetro
    move_agulha_taco(rpm);            // Desenha a posição da agulha de acordo com a velocidade

    
// ---------------------------------------
//  Imprime Valores Numéricos
// ---------------------------------------

    drawValues();                          // Imprime os valores
    
  } while (u8g2.nextPage());

  }
}
