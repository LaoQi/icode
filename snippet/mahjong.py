import random

"""
麻将的听牌判定
13张可分为4种情况,其中两种特殊情况
3 + 3 + 3 + 3 + 1
3 + 3 + 3 + 2 + 2
2 + 2 + 2 + 2 + 2 + 2 + 2
十三幺

后两种先单独处理,前两种可以使用相同的方案,类似迷宫寻径,分析终止条件做递归即可。
因为单个递归遍历的规模较小,没什么优化的必要。
"""

class Context:
    def __init__(self, three:list=[], two:list=[], one:list=[], tips:list=[], exclude:list=[]) -> None:
        self.three = three
        self.two = two
        self.one = one
        self.tips = tips
        self.exclude = exclude

    def add_three(self, t1, t2, t3):
        self.three.append([t1, t2, t3])

    def add_two(self, t1, t2):
        self.two.append([t1, t2])

    def add_one(self, t):
        self.one.append(t)

    def add_tips(self, t):
        self.tips.append(t)

    def is_pass(self):
        if len(self.two) > 2:
            return False
        if len(self.one) > 1:
            return False
        if len(self.two) > 0 and len(self.one) > 0:
            return False
        
        if len(self.two) == 2:
            # 双顺子
            if self.two[0][0] != self.two[0][1] and self.two[1][0] != self.two[1][1]:
                return False
        
        if sum([len(x) for x in self.one + self.two + self.three]) == 13:
            wt = self._waiting_tile()
            if len(list(filter(lambda x: x not in self.exclude, wt))) == 0:
                return False
            
        return True
    
    def is_double_pairs(self):
        return len(self.two) == 2 and self.two[0][0] == self.two[0][1] and self.two[1][0] == self.two[1][1]
    
    def _waiting_tile(self):
        if len(self.one) > 0:
            return self.one[0]
        if len(self.two) == 2:
            if self.is_double_pairs():
                return [self.two[0][0], self.two[1][0]]
            return self.tips

    def waiting_tile(self):
        return list(filter(lambda x : x not in self.exclude, self._waiting_tile()))
    
    def copy(self):
        return Context(self.three[:], self.two[:], self.one[:], self.tips[:], self.exclude[:])
    
    def merge(self, ctx):
        self.three.extend(ctx.three[:])
        self.two.extend(ctx.two[:])
        self.one.extend(ctx.one[:])
        self.tips.extend(ctx.tips[:])
        self.exclude.extend(ctx.exclude[:])
        return self
    
    def __str__(self) -> str:
        return f'{{ {self.three} {self.two} {self.one} {self.is_pass()} }}'


class Mahjong:
    feng = ['🀀','🀁','🀂','🀃','🀄','🀅','🀆',]
    wan = ['🀇','🀈','🀉','🀊','🀋','🀌','🀍','🀎','🀏',]
    suo = ['🀐','🀑','🀒','🀓','🀔','🀕','🀖','🀗','🀘',]
    tong = ['🀙','🀚','🀛','🀜','🀝','🀞','🀟','🀠','🀡',]
    
    def __init__(self):

        pool = (self.feng + self.wan + self.suo + self.tong) * 4
        
        self.my_tiles = []

        for _ in range(13):
            tile = pool.pop(random.randint(0, len(pool) - 1))
            self.my_tiles.append(tile)

        self.my_tiles.sort()

    def _get_prev_tile(self, t):
        m = [self.wan, self.suo, self.tong]
        for tile in m:
            if t in tile:
                i = tile.index(t)
                if i > 0:
                    return tile[i - 1]
        return False
    
    def _get_next_tile(self, t):
        m = [self.wan, self.suo, self.tong]
        for tile in m:
            if t in tile:
                i = tile.index(t)
                if i < 8:
                    return tile[i + 1]
        return False
    
    def _get_next_next_tile(self, t):
        m = [self.wan, self.suo, self.tong]
        for tile in m:
            if t in tile:
                i = tile.index(t)
                if i < 7:
                    return tile[i + 2]
        return False

    def check(self, tiles):
        tiles.sort()
        print('牌堆', tiles)
        # 额外判定十三幺，对对胡
        result = self.is_seven_pairs(tiles[:]) or self.is_the_thirteen(tiles[:])
        if result:
            print('特殊听牌:', result)
        
        result_list = self.is_waiting_for_win(tiles[:])
        if len(result_list) > 0:
            wt = set()
            for r in result_list:
                print(r, r.waiting_tile())
                [ wt.add(x) for x in r.waiting_tile()]
            print('听牌', sorted(list(wt)))
        return False
        

    def is_waiting_for_win(self, tiles: list):
        # 排除已有4张
        exclude = []
        duplicate_tile = None
        duplicate_count = 0
        for t in tiles:
            if t != duplicate_tile:
                duplicate_tile = t
                duplicate_count = 1
                continue
            else:
                duplicate_count += 1
            if duplicate_count == 4:
                exclude.append(duplicate_tile)

        # 优先检查字牌
        feng = list(filter(lambda x: x < '🀇', tiles))
        fctx = Context([], [], [], [], exclude[:])
        if len(feng) > 0:
            fctx = self._check_feng(feng, fctx)
        
        # '🀇','🀏','🀐','🀘','🀙','🀡'
        result1 = self._check_normal(list(filter(lambda x: x >= '🀇' and x <= '🀏', tiles)), Context([], [], [], [], exclude[:]))
        result2 = self._check_normal(list(filter(lambda x: x >= '🀐' and x <= '🀘', tiles)), Context([], [], [], [], exclude[:]))
        result3 = self._check_normal(list(filter(lambda x: x >= '🀙' and x <= '🀡', tiles)), Context([], [], [], [], exclude[:]))

        result = []
        for r1 in result1:
            for r2 in result2:
                for r3 in result3:
                   rt = fctx.copy()
                   rt.merge(r1).merge(r2).merge(r3)
                   result.append(rt)

        return list(filter(lambda x: x.is_pass(), result))
        

    def _check_normal(self, tiles: list, context: Context):
        if not context.is_pass():
            return []
        
        length = len(tiles)
        if length == 0:
            return [context]
        
        ctx = context.copy()
        if length == 1:
            ctx.add_one(tiles[:])
            return [ctx]        

        if length == 2:
            # 对子
            if tiles[0] == tiles[1]:
                ctx.add_two(tiles[0], tiles[1])
                return [ctx]
            # 边张
            if self._get_next_tile(tiles[0]) == tiles[1]:
                ctx.add_two(tiles[0], tiles[1])
                prev = self._get_prev_tile(tiles[0])
                if prev:
                    ctx.add_tips(prev)
                next_tile = self._get_next_tile(tiles[1])
                if next_tile:
                    ctx.add_tips(next_tile)

                return [ctx]
            # 夹张
            if self._get_next_next_tile(tiles[0]) == tiles[1]:
                ctx.add_two(tiles[0], tiles[1])
                ctx.add_tips(self._get_next_tile(tiles[0]))
                return [ctx]
            # 啥也不是
            return []

        if length == 3:
            ctx1 = ctx.copy()
            ctx1.add_one(tiles[2])
            ctx2 = ctx.copy()
            ctx2.add_one(tiles[0])
            # 刻子
            if tiles[0] == tiles[1] and tiles[1] == tiles[2]:
                ctx.add_three(tiles[0], tiles[1], tiles[2])
                return [ctx] + self._check_normal(tiles[:2], ctx1) + self._check_normal(tiles[1:], ctx2)
            
            # 顺子
            if self._get_next_tile(tiles[0]) == tiles[1] and self._get_next_tile(tiles[1]) == tiles[2]:
                ctx.add_three(tiles[0], tiles[1], tiles[2])
                return [ctx] + self._check_normal(tiles[:2], ctx1) + self._check_normal(tiles[1:], ctx2)
            
            return []
        else:

            ctx1 = ctx.copy()
            ctx2 = ctx.copy()
            result = []

            # 刻子
            if tiles[0] == tiles[2]:
                ctx1.add_three(tiles[0], tiles[1], tiles[2])
                result.extend(self._check_normal(tiles[3:], ctx1))
            # 对子
            elif tiles[0] == tiles[1]:
                ctx2.add_two(tiles[0], tiles[1])
                result.extend(self._check_normal(tiles[2:], ctx2))

            # 顺子
            i = 1
            next_tile = self._get_next_tile(tiles[0])
            next_next_tile = self._get_next_next_tile(tiles[0])
            ctx3 = ctx.copy()
            ctx4 = ctx.copy()
            ctx5 = ctx.copy()
            
            while i < length:
                if tiles[i] == next_tile:
                    # 边张
                    ctx3.add_two(tiles[0], tiles[i])
                    prev_tips = self._get_prev_tile(tiles[0])
                    if prev_tips:
                        ctx3.add_tips(prev_tips)
                    next_tips = self._get_next_tile(tiles[i])
                    if next_tips:
                        ctx3.add_tips(next_tips)

                    result.extend(self._check_normal(tiles[1:i] + tiles[i + 1:], ctx3))
                    j = i + 1
                    while j < length:
                        if tiles[j] == next_next_tile:
                            ctx4.add_three(tiles[0], tiles[i], tiles[j])
                            result.extend(self._check_normal(tiles[1:i] + tiles[i + 1:j] + tiles[j + 1:], ctx4))
                            break
                        j += 1
                    break
                if tiles[i] == next_next_tile:
                    # 夹张
                    ctx5.add_two(tiles[0], tiles[i])
                    ctx5.add_tips(next_tile)

                    result.extend(self._check_normal(tiles[1:i] + tiles[i + 1:], ctx5))
                i += 1

            # 单钓
            ctx6 = ctx.copy()
            ctx6.add_one(tiles[0])
            result.extend(self._check_normal(tiles[1:], ctx6))

            return result
            

    def _check_feng(self, tiles: list, context: Context) -> Context:
        length = len(tiles)
        i = 0
        while i < length:
            t = tiles[i]
            # 刻子
            if length - i > 2 and t == tiles[i + 2]:
                context.add_three(t, tiles[i + 1], tiles[i + 2])
                i = i + 2
            # 对子
            elif length - i > 1 and t == tiles[i + 1]:
                context.add_two(t, tiles[i + 1])
                i = i + 1
            else:
                context.add_one(t)
            if not context.is_pass():
                return context
            i += 1
        return context

    def is_seven_pairs(self, tiles: list):
        # 对对胡
        stack = []
        stack.append(tiles.pop())
        while len(tiles) > 0:
            t = tiles.pop()
            if len(stack) == 0:
                stack.append(t)
                continue
            m = stack.pop()
            if m == t:
                continue
            else:
                stack.append(m)
                stack.append(t)
            if len(stack) > 1:
                return False
        return [stack.pop()]

    
    def is_the_thirteen(self, tiles: list):
        # 十三幺
        thirteen = set(['🀀','🀁','🀂','🀃','🀄','🀅','🀆','🀇','🀏','🀐','🀘','🀙','🀡',])
        tset = set(tiles)
        
        diff = tset - thirteen
        if len(diff) > 0:
            return False
        
        rdiff = thirteen - tset
        if len(rdiff) == 0:
            return list(thirteen)
        elif len(rdiff) > 1:
            return False
        return [rdiff.pop()]
    
    def test_find(self, t):
        print(self._get_next_tile(t))
        print(self._get_prev_tile(t))           



def testThirteen(mj):
    print(mj.is_the_thirteen(['🀀','🀖','🀂','🀃','🀄','🀅','🀆','🀇','🀏','🀐','🀘','🀙','🀡',]))
    print(mj.is_the_thirteen(['🀅','🀆','🀇','🀏','🀐','🀘','🀙','🀡','🀀','🀁','🀂','🀃','🀄',]))
    print(mj.is_the_thirteen(['🀀','🀃','🀂','🀃','🀄','🀅','🀆','🀇','🀏','🀐','🀘','🀙','🀡',]))

def testSevenPairs(mj):
    print(mj.is_seven_pairs(sorted(['🀀','🀖','🀂','🀃','🀄','🀅','🀆','🀖','🀂','🀃','🀄','🀅','🀆',])))
    print(mj.is_seven_pairs(sorted(['🀖','🀖','🀂','🀃','🀄','🀅','🀆','🀖','🀂','🀃','🀄','🀅','🀆',])))

def testCheck(mj):
    print(mj.check(['🀀','🀖','🀂','🀃','🀄','🀅','🀆','🀇','🀏','🀐','🀘','🀙','🀡',]))
    print(mj.check(['🀅','🀆','🀇','🀏','🀐','🀘','🀙','🀡','🀀','🀁','🀂','🀃','🀄',]))
    print(mj.check(['🀀','🀃','🀂','🀃','🀄','🀅','🀆','🀇','🀏','🀐','🀘','🀙','🀡',]))
    print(mj.check(['🀀','🀖','🀂','🀃','🀄','🀅','🀆','🀖','🀂','🀃','🀄','🀅','🀆',]))
    print(mj.check(['🀖','🀖','🀂','🀃','🀄','🀅','🀆','🀖','🀂','🀃','🀄','🀅','🀆',]))

if __name__ == "__main__":
    # ['🀀','🀁','🀂','🀃','🀄','🀅','🀆',]
    # ['🀇','🀈','🀉','🀊','🀋','🀌','🀍','🀎','🀏',]
    # ['🀐','🀑','🀒','🀓','🀔','🀕','🀖','🀗','🀘',]
    # ['🀙','🀚','🀛','🀜','🀝','🀞','🀟','🀠','🀡',]
    
    m = Mahjong()
    ts = ['🀀','🀀','🀀','🀀','🀃','🀃','🀃','🀄','🀄','🀄','🀅','🀅','🀅',]
    
    # m.check(['🀀','🀀','🀀','🀃','🀃','🀇','🀈','🀉','🀐','🀑','🀒','🀙','🀙',]) # 普通
    # m.check(['🀇','🀇','🀇','🀈','🀉','🀊','🀋','🀌','🀍','🀎','🀏','🀏','🀏',]) # 九连宝灯
    m.check(['🀅','🀆','🀇','🀏','🀐','🀘','🀙','🀡','🀀','🀁','🀂','🀃','🀄',]) # 十三幺
    m.check(['🀈','🀉','🀉','🀉','🀉','🀊','🀊','🀋','🀌','🀍','🀎','🀎','🀎',]) # 八连宝灯（八面听）
    m.check(['🀈','🀉','🀉','🀉','🀉','🀊','🀋','🀌','🀍','🀍','🀍','🀍','🀎',]) # 七连宝灯（七面听）
    m.check(['🀇','🀇','🀇','🀈','🀉','🀊','🀋','🀌','🀍','🀎','🀙','🀙','🀙',])  # 六面听
    m.check(['🀉','🀉','🀊','🀊','🀋','🀋','🀌','🀌','🀜','🀝','🀞','🀞','🀞',])  # 四面听

    