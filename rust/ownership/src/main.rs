fn main() {
    let v = vec![0,1,2,3];
    let v2 = v;
    for i in v2.clone() {
        println!("i is {}", i);
    }
    for i in v2 {
        println!("i is {}", i);
    }
}
