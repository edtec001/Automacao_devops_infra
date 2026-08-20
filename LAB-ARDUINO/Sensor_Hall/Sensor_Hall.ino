/*
 ==================================================================
 INJETOR DE SINAIS VERSÃO DEMO SIMPLIFICADA - LEIAME-ME COM ATENÇÃO
 ==================================================================

 Importante: Não há suporte para esse software. Altere-o à vontade.

 Este injetor de sinais utiliza placa Arduino UNO ou compatíveis e um display
 shield com keys de navegação. É capaz de gerar diversos sinais, desde os mais
 simples ATÉ os mais complexos. Nesta versão SIMPLIFICADA está contemplada
 apenas os sinais tradicionais 36-2 e 44-4 tipo HALL utilizado em grande parte
 dos veículos equipados com motores de quatro tempos.

 O sinal Hall é obtido na saída digital D11, com amplitude de 5 volts.

   ======================================
  ALIMENTAÇÃO E ATERRAMENTO - IMPORTANTE
  Quando usado para geração de sinal CKP (indutivo) o dispositivo deve ser
 alimentado com fonte de alimentação AUTÔNOMA entre 9 e 15 volts e NENHUM fio
 deve ser aterrado na carcaça do veículo ou do módulo de injeção. Apenas os dois
 fios do sinal indutivo devem ser usados.

  Quando usado para geração de SINAL HALL, com ou sem FASE, o dispositivo pode
 ser alimentado com fonte de alimentação AUTÔNOMA entre 9 e 15 volts ou então a
 própria bateria do veículo. Neste caso, o terra do dispositivo DEVE SER ligado
 à carcaça do veículo ou ao negativo da bateria.

  =======================
  BIBLIOTECAS NECESSÁRIAS
  Para evitar erros de compilação ou execução é necessário o uso das bibliotecas
 "TimerOne.h" e "LiquidCrystal.h". Normalmente, a biblioteca "LiquidCrystal.h"
 já vem com a plataforma Arduino, enquanto a "TimerOne.h" precisa ser instalada.

  ==============
  SINAIS GERADOS
  Todos os sinais simulados na versão FULL foram testados previamente e devem
 produzir bons resultados na maioria das centrais eletrônicas. O seguinte vídeo
 mostra a versão full (COMPLETA) do software em funcionamento, acionando através
 de sinal indutivo uma central de injeção IAW 4AF.PF:
  https://youtu.be/0VwI9AU6dKQ

  ==============
  NOTA IMPORTANTE  ---  NOTA IMPORTANTE  ---  NOTA IMPORTANTE  ---
  Este software é baseado em técnicas de programação e conhecimento de como
 funcionam as rodas fônicas e esquema de fase e sincronismo dos motores. Não há
 segredos de estado e devido às limitações impostas pela programação do Arduino,
 muitas rotinas e funções podem ser parecidas com aquelas criadas por outros
  programação e a citação da fonte deve ser uma gentileza.

  ==============
*/

#include <LiquidCrystal.h>
#include <TimerOne.h>

// Protótipos de Funções
void teclado();
void checa_opcao();
void velocidade();
void roda_fonica();
void reset_default();
void menu_principal();
void menu_sinais_simples();

LiquidCrystal lcd(8, 9, 4, 5, 6, 7);
String nome_do_sinal = "60-2";
int dentes_da_roda = 120;
int dentes_totais = 60;
int falhas = 2;

// float pot = 0;
// int tick_1 = 1;
// int menu = 0;

int btn = 0;
int key = 0;
float rot_por_minuto = 1000;
float rot_por_segundo = 0;
float tempo = 0;
int mark = 0;
float v_pot = 0;
int dentes = 1;

int prompt = 13;
int start = 0;   // Enable os sinais
int submenu = 0; // Tipo de submenu: 1 = menu de sinais simples
int roda = 0;    // roda default
int opc_menu = 0;

// -------------
// Versao Free / Custom
// -------------
int contador = 0;
int cont_virab = 0;

void sincro_comando() {
  // Gera 1 pulso de 1 dente na segunda volta do virabrequim
  if (cont_virab > 3) {
    cont_virab = 0;
  }
  cont_virab = cont_virab + 1;

  if (cont_virab == 1 || cont_virab == 3) {
    if (contador == 1) {
      digitalWrite(3, HIGH);
    } else {
      digitalWrite(3, LOW);
    }
  }
}

void lcd_opcoes_basicas() {
  lcd.setCursor(0, 1);
  lcd.print("[SELECT] [U] [D]");
}
void reset_default();

void setup() {
  lcd.begin(16, 2);
  // Serial.begin(9600);            // open the serial port at 9600 bps:

  pinMode(11, OUTPUT); // Hall
  pinMode(12, OUTPUT); // Indutivo fase 2
  pinMode(13, OUTPUT); // Indutivo fase 1
  pinMode(3, OUTPUT);  // Fase

  lcd.setCursor(0, 0);
  lcd.print("Injetor de Sinal");
  lcd.setCursor(3, 1);
  lcd.print("Rotacao e fase");
  delay(2000);

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Injetor Multissinal");
  lcd.setCursor(0, 1);
  lcd.print("Hall e Indutivo");
  delay(2000);

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Selecione");
  lcd.setCursor(0, 1);
  lcd.print("o sinal desejado");
  delay(1000);

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("H = Sinal Hall");
  lcd.setCursor(0, 1);
  lcd.print("I = Indutivo CKP");
  delay(1500);
  lcd.clear();

  // O menor tempo aceito é 1 microsegundo e o tempo máximo é de 8388480
  // micro-segundos (8,3 segundos) Se nenhum valor é passado o valor default
  // será de de 1000000 microsegundos (1 segundo). Quando usado o timer1 o
  // analogWrite() nos pinos 9 e 10 do Arduino param de funcionar.

  // Inicialmente chama a roda fônica a cada 200 mil microsegundos (0,2 segundo)
  Timer1.initialize(200000);
  Timer1.attachInterrupt(roda_fonica);
}

void loop() {

  // ----------------
  // [0] - Menu principal:  Permite escolher entre sinais comuns ou sinais
  // sincronizados
  if (submenu == 0) {
    menu_principal();
  }

  // Menu de sinais simples:
  if (submenu == 1) {
    if (roda == 0) roda = 101;
    menu_sinais_simples();
  }

  // Menu de sinais sincronizados:
  if (submenu == 2) {
    opc_menu = 0;
    submenu = 0;
    delay(250);
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("  Indisponivel");
    delay(1500);
    lcd.clear();
    reset_default();
  }

  // --------------------
  // Gera Sinais Basicos
  // --------------------

  if (roda == 101) {
    nome_do_sinal = "60-2";
    dentes_totais = 60;
    falhas = 2;
    dentes_da_roda = 120;
    velocidade();
  }

  if (roda == 102) {
    nome_do_sinal = "44-4";
    dentes_totais = 44;
    falhas = 4;
    dentes_da_roda = 88;
    velocidade();
  }

  if (roda == 103) {
    nome_do_sinal = "36-2";
    dentes_totais = 36;
    falhas = 2;
    dentes_da_roda = 72;
    velocidade();
  }

  if (roda == 104) {
    nome_do_sinal = "36-1";
    dentes_totais = 36;
    falhas = 1;
    dentes_da_roda = 72;
    velocidade();
  }

} // Fim da funcao loop

