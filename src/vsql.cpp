#include <iostream>
#include <fstream>
#include <string>
using namespace std;


string example(){
   string content = "";
   string line;

   ifstream myfile ("example.vsql");
   if (myfile.is_open()){
      while ( getline (myfile,line) ) {
         content += line + '\n';
      }
      myfile.close();
   }

   return content;
}

string find_word(string data, int i){

   return 0;
}


int main() {
   string data = example();

   // current key word found
   string cuw = "";
   
   // add char to cuw
   bool addctw = false;

   for (int i=0; i < data.length(); ++i) {
      char c = data[i];

      if (c == ' '){ 
         cuw = "";
         addctw = false; 
      } else if ( c == ':' ){ 
         addctw = true; 
      } else {
         cuw += c;
      }


      
      cout << c;

   }

   // cout << data; 
   return 0;
}
