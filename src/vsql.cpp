#include <iostream>
#include <fstream>
#include <string>

using namespace std;

string example(){
   string content = "";
   string line;

   ifstream myfile ("example.sql");
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

   // add char to cuw ----------
   int append = 0;

   for (int i = 0; i < data.length(); ++i) {
      char c = data[i];

      if (c == ' '){
        cout << cuw << '\n';
        cuw = "";
        append = 0;
      } else if ( c == ':' ){
        append = 1;
      }

      // cout << c;
      if ( append == 1 ) {
        cuw += c;
      }
   }

   // cout << data;
   return 0;
}
